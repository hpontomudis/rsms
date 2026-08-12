<?php

namespace App\Livewire\Curricula;

use App\Models\Curriculum;
use App\Models\CurriculumScope;
use App\Models\LearningObjective;
use App\Models\LearningOutcome;
use App\Models\Subject;
use App\Models\LearningPathway;
use App\Services\LearningObjectiveService;
use App\Services\LearningPathwayService;
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

    // ------------------------------------------------ learning objectives (TP)

    public bool $showAddObjective = false;

    public ?int $editingObjectiveId = null;

    public ?int $linkingObjectiveId = null;

    public string $objective_subject_id = '';

    public string $objective_code = '';

    public string $objective_title = '';

    public string $objective_text = '';

    public string $link_outcome_id = '';

    public function startAddingObjective(): void
    {
        $this->authorize('create', LearningObjective::class);
        $this->resetObjectiveForm();
        $this->showAddObjective = true;
    }

    public function startEditingObjective(int $objectiveId): void
    {
        $objective = $this->objective($objectiveId);
        $this->authorize('update', $objective);

        $this->resetObjectiveForm();
        $this->editingObjectiveId = $objective->id;
        $this->objective_subject_id = (string) $objective->subject_id;
        $this->objective_code = $objective->code ?? '';
        $this->objective_title = $objective->title ?? '';
        $this->objective_text = $objective->objective_text;
    }

    public function cancelObjective(): void
    {
        $this->resetObjectiveForm();
    }

    private function resetObjectiveForm(): void
    {
        $this->reset([
            'showAddObjective', 'editingObjectiveId', 'linkingObjectiveId',
            'objective_subject_id', 'objective_code', 'objective_title', 'objective_text', 'link_outcome_id',
        ]);
        $this->resetErrorBag();
    }

    public function saveObjective(LearningObjectiveService $objectives): void
    {
        $rules = [
            'objective_code' => ['nullable', 'string', 'max:50'],
            'objective_title' => ['nullable', 'string', 'max:255'],
            'objective_text' => ['required', 'string'],
        ];

        // The anchor is only chosen at creation; afterwards it is identity.
        if (! $this->editingObjectiveId) {
            $rules['objective_subject_id'] = ['required', 'exists:subjects,id'];
        }

        $validated = $this->validate($rules);

        $attributes = [
            'code' => $validated['objective_code'] !== '' ? $validated['objective_code'] : null,
            'title' => $validated['objective_title'] !== '' ? $validated['objective_title'] : null,
            'objective_text' => $validated['objective_text'],
        ];

        if ($this->editingObjectiveId) {
            $objective = $this->objective($this->editingObjectiveId);
            $this->authorize('update', $objective);
            $objective->update($attributes);
        } else {
            $this->authorize('create', LearningObjective::class);
            $objectives->create(
                $this->scope,
                Subject::findOrFail($validated['objective_subject_id']),
                $attributes,
            );
        }

        $this->resetObjectiveForm();
        $this->scope->refresh();
    }

    public function startLinking(int $objectiveId): void
    {
        $objective = $this->objective($objectiveId);
        $this->authorize('update', $objective);

        $this->resetObjectiveForm();
        $this->linkingObjectiveId = $objective->id;
    }

    public function linkOutcome(LearningObjectiveService $objectives): void
    {
        $objective = $this->objective($this->linkingObjectiveId);
        $this->authorize('update', $objective);

        $validated = $this->validate(['link_outcome_id' => ['required', 'exists:learning_outcomes,id']]);

        $objectives->linkOutcome($objective, LearningOutcome::findOrFail($validated['link_outcome_id']));

        $this->link_outcome_id = '';
        $this->scope->refresh();
    }

    public function unlinkOutcome(int $objectiveId, int $outcomeId, LearningObjectiveService $objectives): void
    {
        $objective = $this->objective($objectiveId);
        $this->authorize('update', $objective);

        $objectives->unlinkOutcome($objective, LearningOutcome::findOrFail($outcomeId));

        $this->scope->refresh();
    }

    public function activateObjective(int $objectiveId, LearningObjectiveService $objectives): void
    {
        $objective = $this->objective($objectiveId);
        $this->authorize('transition', $objective);

        $objectives->activate($objective);

        $this->resetObjectiveForm();
        $this->scope->refresh();
    }

    public function archiveObjective(int $objectiveId, LearningObjectiveService $objectives): void
    {
        $objective = $this->objective($objectiveId);
        $this->authorize('transition', $objective);

        $objectives->archive($objective);

        $this->resetObjectiveForm();
        $this->scope->refresh();
    }

    public function deleteObjective(int $objectiveId, LearningObjectiveService $objectives): void
    {
        $objective = $this->objective($objectiveId);
        $this->authorize('delete', $objective);

        $objectives->delete($objective);

        $this->scope->refresh();
    }

    /**
     * Move a draft up or down the reference library. Only ACTIVE rows are
     * constrained to unique order, so a plain swap is safe here -- but the
     * order is captured before either write, because update() re-syncs a
     * model's original attributes.
     */
    public function moveObjective(int $objectiveId, string $direction): void
    {
        $objective = $this->objective($objectiveId);
        $this->authorize('update', $objective);

        $siblings = $this->scope->learningObjectives()
            ->where('subject_id', $objective->subject_id)->get();

        $neighbour = $direction === 'up'
            ? $siblings->where('reference_order', '<', $objective->reference_order)->last()
            : $siblings->where('reference_order', '>', $objective->reference_order)->first();

        if (! $neighbour || ! $neighbour->isDraft()) {
            return;
        }

        $mine = $objective->reference_order;
        $theirs = $neighbour->reference_order;

        \DB::transaction(function () use ($objective, $neighbour, $mine, $theirs) {
            $neighbour->update(['reference_order' => $mine]);
            $objective->update(['reference_order' => $theirs]);
        });

        $this->scope->refresh();
    }

    private function objective(int $id): LearningObjective
    {
        return $this->scope->learningObjectives()->findOrFail($id);
    }

    // ---------------------------------------------- learning pathways (ATP)

    public bool $showAddPathway = false;

    public string $pathway_subject_id = '';

    public string $pathway_code = '';

    public string $pathway_title = '';

    public string $pathway_description = '';

    public function cancelPathway(): void
    {
        $this->reset(['showAddPathway', 'pathway_subject_id', 'pathway_code', 'pathway_title', 'pathway_description']);
        $this->resetErrorBag();
    }

    public function savePathway(LearningPathwayService $pathways): void
    {
        $validated = $this->validate([
            'pathway_subject_id' => ['required', 'exists:subjects,id'],
            'pathway_code' => ['nullable', 'string', 'max:50'],
            'pathway_title' => ['required', 'string', 'max:255'],
            'pathway_description' => ['nullable', 'string'],
        ]);

        // Teachers may author, but only for a scope + subject they actually
        // teach -- the policy resolves that through their active assignments.
        $this->authorize('createFor', [LearningPathway::class, $this->scope, (int) $validated['pathway_subject_id']]);

        $pathways->create($this->scope, Subject::findOrFail($validated['pathway_subject_id']), [
            'code' => $validated['pathway_code'] !== '' ? $validated['pathway_code'] : null,
            'title' => $validated['pathway_title'],
            'description' => $validated['pathway_description'] !== '' ? $validated['pathway_description'] : null,
        ]);

        $this->cancelPathway();
        $this->scope->refresh();
    }

    public function render(LearningObjectiveService $objectives)
    {
        return view('livewire.curricula.scope-show', [
            'outcomes' => $this->scope->learningOutcomes()->with('subject')->get()
                ->sortBy([fn ($a, $b) => $a->subject->name <=> $b->subject->name, fn ($a, $b) => $a->sequence <=> $b->sequence])
                ->groupBy(fn ($outcome) => $outcome->subject->name),
            'subjects' => Subject::orderBy('name')->get(),
            'vocabulary' => $this->curriculum->vocabulary(),
            'editable' => $this->editable(),
            'grades' => $this->scope->grades(),
            'objectives' => $this->scope->learningObjectives()
                ->with(['subject', 'outcomeLinks.learningOutcome'])->get()
                ->sortBy([
                    fn ($a, $b) => $a->subject->name <=> $b->subject->name,
                    fn ($a, $b) => $a->reference_order <=> $b->reference_order,
                ])
                ->groupBy(fn ($objective) => $objective->subject->name),
            // Objectives may still be authored while the curriculum is active;
            // only an archived curriculum closes the library.
            'objectivesEditable' => ! $this->curriculum->isArchived(),
            'pathways' => $this->scope->learningPathways()
                ->with('subject')->withCount('items')->get()
                ->sortBy([
                    fn ($a, $b) => $a->subject->name <=> $b->subject->name,
                    fn ($a, $b) => $a->title <=> $b->title,
                ])
                ->groupBy(fn ($pathway) => $pathway->subject->name),
            // Whether this user could author a pathway for ANY subject here --
            // the per-subject check happens on save.
            'canPlan' => auth()->user()->can('academics.plan') || auth()->user()->can('academics.manage'),
            'linkableOutcomes' => $this->linkingObjectiveId
                ? $objectives->linkableOutcomes($this->objective($this->linkingObjectiveId))
                : collect(),
        ]);
    }
}
