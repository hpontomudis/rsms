<?php

namespace App\Livewire\Curricula;

use App\Models\Curriculum;
use App\Models\CurriculumScope;
use App\Models\LearningOutcome;
use App\Models\Subject;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * One scope of a curriculum version, and the learning outcomes it states.
 *
 * The same screen serves a national Fase and a Rahai English level; the words
 * change (Capaian Pembelajaran vs Learning Outcome), the machinery does not.
 * Everything is editable while the curriculum is a draft and read-only after,
 * which the model guard enforces regardless of what this component allows.
 */
#[Layout('layouts.app')]
class ScopeShow extends Component
{
    public Curriculum $curriculum;

    public CurriculumScope $scope;

    public bool $showAddOutcome = false;

    public ?int $editingId = null;

    public string $subject_id = '';

    public string $code = '';

    public string $title = '';

    public string $outcome_text = '';

    public function mount(Curriculum $curriculum, CurriculumScope $scope): void
    {
        $this->authorize('view', $scope);

        abort_unless($scope->curriculum_id === $curriculum->id, 404);

        $this->curriculum = $curriculum;
        $this->scope = $scope;
    }

    private function editable(): bool
    {
        return $this->curriculum->isDraft();
    }

    public function startAdding(): void
    {
        $this->authorize('create', LearningOutcome::class);
        $this->reset(['editingId', 'subject_id', 'code', 'title', 'outcome_text']);
        $this->showAddOutcome = true;
        $this->resetErrorBag();
    }

    public function startEditing(int $outcomeId): void
    {
        $outcome = $this->scope->learningOutcomes()->findOrFail($outcomeId);
        $this->authorize('update', $outcome);

        $this->editingId = $outcome->id;
        $this->showAddOutcome = false;
        $this->subject_id = (string) $outcome->subject_id;
        $this->code = $outcome->code ?? '';
        $this->title = $outcome->title ?? '';
        $this->outcome_text = $outcome->outcome_text;
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->reset(['showAddOutcome', 'editingId', 'subject_id', 'code', 'title', 'outcome_text']);
    }

    public function save(): void
    {
        $validated = $this->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'code' => ['nullable', 'string', 'max:50'],
            'title' => ['nullable', 'string', 'max:255'],
            // No max: an official CP narrative is paragraphs, and the column
            // is TEXT precisely so it is not truncated.
            'outcome_text' => ['required', 'string'],
        ]);

        $attributes = [
            'subject_id' => $validated['subject_id'],
            'code' => $validated['code'] !== '' ? $validated['code'] : null,
            'title' => $validated['title'] !== '' ? $validated['title'] : null,
            'outcome_text' => $validated['outcome_text'],
        ];

        if ($this->editingId) {
            $outcome = $this->scope->learningOutcomes()->findOrFail($this->editingId);
            $this->authorize('update', $outcome);
            $outcome->update($attributes);
        } else {
            $this->authorize('create', LearningOutcome::class);

            // Appended to the end of this subject's outcomes in this scope --
            // several outcomes per subject is the normal case when an official
            // CP is broken into elements.
            $nextSequence = ((int) $this->scope->learningOutcomes()
                ->where('subject_id', $attributes['subject_id'])->max('sequence')) + 1;

            LearningOutcome::create($attributes + [
                'curriculum_scope_id' => $this->scope->id,
                'sequence' => $nextSequence,
            ]);
        }

        $this->cancel();
        $this->scope->refresh();
    }

    /**
     * Swap an outcome with its neighbour within the same subject. Uses a
     * sentinel because unique(scope, subject, sequence) would otherwise be
     * violated halfway through the swap.
     */
    public function move(int $outcomeId, string $direction): void
    {
        $outcome = $this->scope->learningOutcomes()->findOrFail($outcomeId);
        $this->authorize('update', $outcome);

        $siblings = $this->scope->learningOutcomes()->where('subject_id', $outcome->subject_id)->get();

        $neighbour = $direction === 'up'
            ? $siblings->where('sequence', '<', $outcome->sequence)->last()
            : $siblings->where('sequence', '>', $outcome->sequence)->first();

        if (! $neighbour) {
            return;
        }

        // Both captured before either write: update() re-syncs a model's
        // original attributes, so reading the neighbour's old value afterwards
        // returns the new one and the swap collides.
        $mine = $outcome->sequence;
        $theirs = $neighbour->sequence;

        \DB::transaction(function () use ($outcome, $neighbour, $mine, $theirs) {
            $outcome->update(['sequence' => 0]);
            $neighbour->update(['sequence' => $mine]);
            $outcome->update(['sequence' => $theirs]);
        });

        $this->scope->refresh();
    }

    public function remove(int $outcomeId): void
    {
        $outcome = $this->scope->learningOutcomes()->findOrFail($outcomeId);
        $this->authorize('delete', $outcome);

        $outcome->delete();

        $this->scope->refresh();
    }

    public function render()
    {
        return view('livewire.curricula.scope-show', [
            'outcomes' => $this->scope->learningOutcomes()->with('subject')->get()
                ->sortBy([fn ($a, $b) => $a->subject->name <=> $b->subject->name, fn ($a, $b) => $a->sequence <=> $b->sequence])
                ->groupBy(fn ($outcome) => $outcome->subject->name),
            'subjects' => Subject::orderBy('name')->get(),
            'vocabulary' => $this->curriculum->vocabulary(),
            'editable' => $this->editable(),
            'grades' => $this->scope->grades(),
        ]);
    }
}
