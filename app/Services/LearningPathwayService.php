<?php

namespace App\Services;

use App\Models\CurriculumScope;
use App\Models\LearningObjective;
use App\Models\LearningPathway;
use App\Models\LearningPathwayItem;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Authoring rules for learning pathways (ATP).
 *
 * The model guards identity and the draft/active/archived freeze; this owns
 * the sequence -- what may be added, in what order, and what must hold before
 * a draft is put into force.
 *
 * POSITION INTEGRITY IS APPLICATION-LEVEL. A partial unique index on
 * (pathway, position) would have to know the parent's status, which SQL cannot
 * see from an index predicate, and mirroring status onto every item purely to
 * enable one index was judged worse than the rule living here. So every write
 * path below re-normalises a draft's positions to a contiguous 1..n inside the
 * same transaction, and activation validates it again as a final gate. Raw SQL
 * can still leave a draft gapped; that is documented, not hidden.
 */
class LearningPathwayService
{
    public function create(CurriculumScope $scope, Subject $subject, array $attributes): LearningPathway
    {
        $this->assertCurriculumNotArchived($scope);

        return LearningPathway::create([
            'curriculum_scope_id' => $scope->id,
            'subject_id' => $subject->id,
            'code' => $attributes['code'] ?? null,
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'status' => 'draft',
        ]);
    }

    /**
     * Append an objective to the end of the sequence.
     */
    public function addItem(LearningPathway $pathway, LearningObjective $objective, ?string $notes = null): LearningPathwayItem
    {
        $this->assertDraft($pathway, 'have items added');

        if ($objective->curriculum_scope_id !== $pathway->curriculum_scope_id
            || $objective->subject_id !== $pathway->subject_id) {
            $this->fail('learning_objective_id', 'That objective belongs to a different curriculum scope or subject.');
        }

        // A retired objective is not something to build a NEW plan on. An
        // objective archived AFTER a pathway went active is a different
        // question -- see activate(), and the history stays untouched.
        if ($objective->isArchived()) {
            $this->fail('learning_objective_id', "{$objective->code} is archived and cannot be added to a new sequence.");
        }

        if ($pathway->items()->where('learning_objective_id', $objective->id)->exists()) {
            $this->fail('learning_objective_id', 'That objective is already in this pathway.');
        }

        return DB::transaction(function () use ($pathway, $objective, $notes) {
            $item = LearningPathwayItem::create([
                'learning_pathway_id' => $pathway->id,
                'learning_objective_id' => $objective->id,
                // Mirrored from the pathway, never from the caller.
                'curriculum_scope_id' => $pathway->curriculum_scope_id,
                'subject_id' => $pathway->subject_id,
                'position' => $this->nextPosition($pathway),
                'notes' => $notes,
            ]);

            $this->normalise($pathway);

            return $item->refresh();
        });
    }

    public function removeItem(LearningPathway $pathway, LearningPathwayItem $item): void
    {
        $this->assertDraft($pathway, 'have items removed');

        DB::transaction(function () use ($pathway, $item) {
            $item->delete();
            // Closes the gap the removal just made.
            $this->normalise($pathway);
        });
    }

    /**
     * Swap an item with its neighbour. A plain swap is safe because drafts
     * carry no positional unique index -- the collision dance that class-level
     * reordering needs is unnecessary here.
     */
    public function moveItem(LearningPathway $pathway, LearningPathwayItem $item, string $direction): void
    {
        $this->assertDraft($pathway, 'be reordered');

        $items = $pathway->items()->get();

        $neighbour = $direction === 'up'
            ? $items->where('position', '<', $item->position)->last()
            : $items->where('position', '>', $item->position)->first();

        if (! $neighbour) {
            return;
        }

        // Both captured before either write: update() re-syncs a model's
        // original attributes.
        $mine = $item->position;
        $theirs = $neighbour->position;

        DB::transaction(function () use ($pathway, $item, $neighbour, $mine, $theirs) {
            $neighbour->update(['position' => $mine]);
            $item->update(['position' => $theirs]);

            $this->normalise($pathway);
        });
    }

    /**
     * Renumber to a contiguous 1..n in current order, ties broken by id.
     *
     * Only rows whose position actually changes are written, so a no-op
     * normalisation produces no audit noise.
     */
    public function normalise(LearningPathway $pathway): void
    {
        $ordered = LearningPathwayItem::where('learning_pathway_id', $pathway->id)
            ->orderBy('position')->orderBy('id')->get();

        foreach ($ordered->values() as $index => $item) {
            $expected = $index + 1;

            if ($item->position !== $expected) {
                $item->update(['position' => $expected]);
            }
        }
    }

    private function nextPosition(LearningPathway $pathway): int
    {
        return ((int) $pathway->items()->max('position')) + 1;
    }

    /**
     * Put a draft sequence into force. Everything is validated before anything
     * is written, and the write runs in a transaction.
     */
    public function activate(LearningPathway $pathway): LearningPathway
    {
        // 2. Must currently be a draft.
        if (! $pathway->isDraft()) {
            $this->fail('status', 'Only a draft learning pathway can be activated.');
        }

        $curriculum = $pathway->curriculumScope->curriculum;

        // 1. The curriculum must be in force.
        if (! $curriculum->isActive()) {
            $this->fail('status', $curriculum->isArchived()
                ? "{$curriculum->name} is archived; nothing new can be put into force under it."
                : "{$curriculum->name} is still a draft. Activate the curriculum version first.");
        }

        // 3. A pathway needs a name.
        if (trim((string) $pathway->title) === '') {
            $this->fail('title', 'A learning pathway needs a title before it can be activated.');
        }

        $items = $pathway->items()->with('learningObjective')->get();

        // 4. An empty sequence is not a sequence.
        if ($items->isEmpty()) {
            $this->fail('items', 'Add at least one learning objective before activating this pathway.');
        }

        foreach ($items as $item) {
            // 5. Everything in force must reference something in force.
            if (! $item->learningObjective->isActive()) {
                $this->fail('items', "{$item->learningObjective->code} is still {$item->learningObjective->status}. Every objective in an active pathway must itself be active.");
            }

            // 6. Belt and braces over the composite keys.
            if ($item->learningObjective->curriculum_scope_id !== $pathway->curriculum_scope_id
                || $item->learningObjective->subject_id !== $pathway->subject_id) {
                $this->fail('items', 'An item no longer matches this pathway\'s scope and subject.');
            }
        }

        // 8. Two pathways in force cannot answer to the same code. Note there
        //    is deliberately NO one-active-per-anchor rule: alternatives may
        //    legitimately run side by side.
        if ($pathway->code !== null) {
            $clash = LearningPathway::where('curriculum_scope_id', $pathway->curriculum_scope_id)
                ->where('subject_id', $pathway->subject_id)
                ->where('code', $pathway->code)
                ->whereKeyNot($pathway->id)
                ->active()
                ->exists();

            if ($clash) {
                $this->fail('code', "Another active pathway already uses the code {$pathway->code}. Archive it first, or give this one a different code.");
            }
        }

        return DB::transaction(function () use ($pathway) {
            // 7. Final ordering gate: normalise, then confirm.
            $this->normalise($pathway);

            $positions = LearningPathwayItem::where('learning_pathway_id', $pathway->id)
                ->orderBy('position')->pluck('position');

            if ($positions->duplicates()->isNotEmpty() || $positions->all() !== range(1, $positions->count())) {
                $this->fail('items', 'The sequence could not be normalised to a contiguous order.');
            }

            $pathway->update(['status' => 'active']);

            return $pathway->refresh();
        });
    }

    /**
     * Retire a pathway. Its items stay exactly as they were, including any
     * objective archived since -- the sequence records what was planned.
     *
     * Never automatic: activating an alternative must not silently retire the
     * route someone else is following.
     */
    public function archive(LearningPathway $pathway): LearningPathway
    {
        if ($pathway->isArchived()) {
            return $pathway;
        }

        if ($pathway->isDraft()) {
            $this->fail('status', 'A draft is deleted rather than archived.');
        }

        $pathway->update(['status' => 'archived']);

        return $pathway->refresh();
    }

    public function delete(LearningPathway $pathway): void
    {
        if (! $pathway->isDraft()) {
            $this->fail('status', 'Only an unused draft can be deleted. Archive an active pathway instead.');
        }

        DB::transaction(function () use ($pathway) {
            // Through the models, so each removal is audited.
            $pathway->items()->get()->each->delete();
            $pathway->delete();
        });
    }

    /**
     * Objectives this draft could still sequence: active or draft ones in its
     * own scope and subject that it does not already contain. Archived
     * objectives are excluded -- new plans are not built on retired goals.
     */
    public function addableObjectives(LearningPathway $pathway)
    {
        $taken = $pathway->items()->pluck('learning_objective_id');

        return LearningObjective::where('curriculum_scope_id', $pathway->curriculum_scope_id)
            ->where('subject_id', $pathway->subject_id)
            ->whereIn('status', ['draft', 'active'])
            ->whereNotIn('id', $taken)
            ->orderBy('reference_order')
            ->get();
    }

    private function assertDraft(LearningPathway $pathway, string $what): void
    {
        if (! $pathway->isDraft()) {
            $this->fail('status', "A pathway that is {$pathway->status} cannot {$what}.");
        }
    }

    private function assertCurriculumNotArchived(CurriculumScope $scope): void
    {
        if ($scope->curriculum->isArchived()) {
            $this->fail('curriculum', "{$scope->curriculum->name} is archived. Plan against the current curriculum version instead.");
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
