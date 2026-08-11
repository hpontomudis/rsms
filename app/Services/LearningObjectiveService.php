<?php

namespace App\Services;

use App\Models\CurriculumScope;
use App\Models\LearningObjective;
use App\Models\LearningObjectiveLearningOutcome;
use App\Models\LearningOutcome;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Authoring rules for learning objectives (TP).
 *
 * The model guards identity and the draft/active/archived freeze; this layer
 * owns the workflow -- what may be linked, and what must be true before a
 * draft may be put into force.
 */
class LearningObjectiveService
{
    public function create(CurriculumScope $scope, Subject $subject, array $attributes): LearningObjective
    {
        $this->assertCurriculumNotArchived($scope);

        return LearningObjective::create([
            'curriculum_scope_id' => $scope->id,
            'subject_id' => $subject->id,
            'code' => $attributes['code'] ?? null,
            'title' => $attributes['title'] ?? null,
            'objective_text' => $attributes['objective_text'],
            'reference_order' => $attributes['reference_order']
                ?? $this->nextReferenceOrder($scope, $subject),
            'status' => 'draft',
        ]);
    }

    /**
     * A draft's next free slot in the library. Only ACTIVE rows constrain
     * uniqueness, but drafts are still given a distinct order so a freshly
     * created batch reads sensibly before any of it is activated.
     */
    private function nextReferenceOrder(CurriculumScope $scope, Subject $subject): int
    {
        return ((int) LearningObjective::where('curriculum_scope_id', $scope->id)
            ->where('subject_id', $subject->id)
            ->max('reference_order')) + 1;
    }

    /**
     * Link a CP. Only while the objective is a draft: an active objective's
     * derivation is part of what was published.
     */
    public function linkOutcome(LearningObjective $objective, LearningOutcome $outcome): LearningObjectiveLearningOutcome
    {
        $this->assertDraft($objective, 'change which outcomes it derives from');

        // The database refuses a mismatch through the composite keys; checking
        // here turns a constraint violation into a sentence.
        if ($outcome->curriculum_scope_id !== $objective->curriculum_scope_id
            || $outcome->subject_id !== $objective->subject_id) {
            $this->fail('learning_outcome_id', 'That outcome belongs to a different curriculum scope or subject.');
        }

        $exists = LearningObjectiveLearningOutcome::where('learning_objective_id', $objective->id)
            ->where('learning_outcome_id', $outcome->id)->exists();

        if ($exists) {
            $this->fail('learning_outcome_id', 'That outcome is already linked to this objective.');
        }

        return LearningObjectiveLearningOutcome::create([
            'learning_objective_id' => $objective->id,
            'learning_outcome_id' => $outcome->id,
            // Mirrored from the objective, never from the caller.
            'curriculum_scope_id' => $objective->curriculum_scope_id,
            'subject_id' => $objective->subject_id,
        ]);
    }

    public function unlinkOutcome(LearningObjective $objective, LearningOutcome $outcome): void
    {
        $this->assertDraft($objective, 'change which outcomes it derives from');

        $objective->outcomeLinks()->where('learning_outcome_id', $outcome->id)->first()?->delete();
    }

    /**
     * Put a draft into force.
     *
     * Everything is validated before anything is written, and the write runs
     * in a transaction, so an objective is never half-activated.
     */
    public function activate(LearningObjective $objective): LearningObjective
    {
        if (! $objective->isDraft()) {
            $this->fail('status', 'Only a draft learning objective can be activated.');
        }

        $curriculum = $objective->curriculumScope->curriculum;

        // 1. The curriculum must be in force. Planning against a draft
        //    curriculum is fine; publishing against one is not, because its
        //    standards can still change underneath.
        if (! $curriculum->isActive()) {
            $this->fail('status', $curriculum->isArchived()
                ? "{$curriculum->name} is archived; nothing new can be put into force under it."
                : "{$curriculum->name} is still a draft. Activate the curriculum version first.");
        }

        // 2. Content must exist.
        if (trim((string) $objective->objective_text) === '') {
            $this->fail('objective_text', 'A learning objective needs its statement before it can be activated.');
        }

        // 3. It must trace to at least one outcome. A draft may sit unlinked
        //    while it is being written; something in force may not.
        $links = $objective->outcomeLinks()->with('learningOutcome')->get();

        if ($links->isEmpty()) {
            $this->fail('learning_outcome_id', 'Link at least one learning outcome before activating this objective.');
        }

        // 4. Belt and braces over the composite keys.
        foreach ($links as $link) {
            if ($link->learningOutcome->curriculum_scope_id !== $objective->curriculum_scope_id
                || $link->learningOutcome->subject_id !== $objective->subject_id) {
                $this->fail('learning_outcome_id', 'A linked outcome no longer matches this objective\'s scope and subject.');
            }
        }

        // 5 & 6. The active library must stay unambiguous.
        $this->assertNoActiveConflict($objective);

        return DB::transaction(function () use ($objective) {
            $objective->update(['status' => 'active']);

            return $objective->refresh();
        });
    }

    /**
     * Retire an active objective. Its links and history stay; anything that
     * already referenced it keeps referencing it.
     */
    public function archive(LearningObjective $objective): LearningObjective
    {
        if ($objective->isArchived()) {
            return $objective;
        }

        if ($objective->isDraft()) {
            $this->fail('status', 'A draft is deleted rather than archived.');
        }

        $objective->update(['status' => 'archived']);

        return $objective->refresh();
    }

    public function delete(LearningObjective $objective): void
    {
        if (! $objective->isDraft()) {
            $this->fail('status', 'Only an unused draft can be deleted. Archive an active objective instead.');
        }

        DB::transaction(function () use ($objective) {
            // Written through the models so each removal is audited.
            $objective->outcomeLinks()->get()->each->delete();
            $objective->delete();
        });
    }

    /**
     * What this draft could still be linked to: outcomes in its own scope and
     * subject that it does not already derive from.
     */
    public function linkableOutcomes(LearningObjective $objective)
    {
        $taken = $objective->outcomeLinks()->pluck('learning_outcome_id');

        return LearningOutcome::where('curriculum_scope_id', $objective->curriculum_scope_id)
            ->where('subject_id', $objective->subject_id)
            ->whereNotIn('id', $taken)
            ->orderBy('sequence')
            ->get();
    }

    /**
     * Reference order and code are unique across the ACTIVE library only, so a
     * replacement draft may deliberately carry its predecessor's. That is the
     * revision workflow -- but two objectives cannot be in force at once with
     * the same order or code.
     */
    private function assertNoActiveConflict(LearningObjective $objective): void
    {
        $siblings = LearningObjective::where('curriculum_scope_id', $objective->curriculum_scope_id)
            ->where('subject_id', $objective->subject_id)
            ->whereKeyNot($objective->id)
            ->active();

        if ((clone $siblings)->where('reference_order', $objective->reference_order)->exists()) {
            $this->fail(
                'reference_order',
                "Another active objective already uses reference order {$objective->reference_order}. Archive it first, or change this one's order."
            );
        }

        if ($objective->code !== null
            && (clone $siblings)->where('code', $objective->code)->exists()) {
            $this->fail(
                'code',
                "Another active objective already uses the code {$objective->code}. Archive it first."
            );
        }
    }

    private function assertDraft(LearningObjective $objective, string $what): void
    {
        if (! $objective->isDraft()) {
            $this->fail('status', "An objective that is {$objective->status} cannot {$what}.");
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
