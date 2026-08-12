<?php

namespace App\Livewire\Curricula;

use App\Models\Curriculum;
use App\Models\CurriculumScope;
use App\Models\LearningObjective;
use App\Models\LearningPathway;
use App\Models\LearningPathwayItem;
use App\Services\LearningPathwayService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * One learning pathway and its ordered sequence.
 *
 * The sequence here is the authoritative instructional order -- deliberately
 * independent of the objectives' own reference order, which only sorts the
 * library. The same objectives may run in a different order in a different
 * pathway, and that is the point.
 */
#[Layout('layouts.app')]
class PathwayShow extends Component
{
    public Curriculum $curriculum;

    public CurriculumScope $scope;

    public LearningPathway $pathway;

    public bool $showAddItem = false;

    public string $learning_objective_id = '';

    public string $item_notes = '';

    public ?int $editingNotesId = null;

    public function mount(Curriculum $curriculum, CurriculumScope $scope, LearningPathway $pathway): void
    {
        $this->authorize('view', $pathway);

        abort_unless($scope->curriculum_id === $curriculum->id, 404);
        abort_unless($pathway->curriculum_scope_id === $scope->id, 404);

        $this->curriculum = $curriculum;
        $this->scope = $scope;
        $this->pathway = $pathway;
    }

    public function addItem(LearningPathwayService $pathways): void
    {
        $this->authorize('update', $this->pathway);

        $validated = $this->validate([
            'learning_objective_id' => ['required', 'exists:learning_objectives,id'],
            'item_notes' => ['nullable', 'string'],
        ]);

        $pathways->addItem(
            $this->pathway,
            LearningObjective::findOrFail($validated['learning_objective_id']),
            $validated['item_notes'] !== '' ? $validated['item_notes'] : null,
        );

        $this->reset(['learning_objective_id', 'item_notes', 'showAddItem']);
        $this->pathway->refresh();
    }

    public function removeItem(int $itemId, LearningPathwayService $pathways): void
    {
        $this->authorize('update', $this->pathway);

        $pathways->removeItem($this->pathway, $this->item($itemId));

        $this->pathway->refresh();
    }

    public function moveItem(int $itemId, string $direction, LearningPathwayService $pathways): void
    {
        $this->authorize('update', $this->pathway);

        $pathways->moveItem($this->pathway, $this->item($itemId), $direction);

        $this->pathway->refresh();
    }

    public function startEditingNotes(int $itemId): void
    {
        $this->authorize('update', $this->pathway);

        $item = $this->item($itemId);
        $this->editingNotesId = $item->id;
        $this->item_notes = $item->notes ?? '';
        $this->showAddItem = false;
        $this->resetErrorBag();
    }

    public function saveNotes(): void
    {
        $this->authorize('update', $this->pathway);

        $validated = $this->validate(['item_notes' => ['nullable', 'string']]);

        $this->item($this->editingNotesId)->update([
            'notes' => $validated['item_notes'] !== '' ? $validated['item_notes'] : null,
        ]);

        $this->cancel();
        $this->pathway->refresh();
    }

    public function cancel(): void
    {
        $this->reset(['showAddItem', 'editingNotesId', 'learning_objective_id', 'item_notes']);
        $this->resetErrorBag();
    }

    public function activate(LearningPathwayService $pathways): void
    {
        $this->authorize('transition', $this->pathway);

        $pathways->activate($this->pathway);

        $this->cancel();
        $this->pathway->refresh();
    }

    public function archive(LearningPathwayService $pathways): void
    {
        $this->authorize('transition', $this->pathway);

        $pathways->archive($this->pathway);

        $this->cancel();
        $this->pathway->refresh();
    }

    private function item(int $id): LearningPathwayItem
    {
        return $this->pathway->items()->findOrFail($id);
    }

    public function render(LearningPathwayService $pathways)
    {
        return view('livewire.curricula.pathway-show', [
            'items' => $this->pathway->items()->with('learningObjective')->get(),
            'addableObjectives' => $this->showAddItem ? $pathways->addableObjectives($this->pathway) : collect(),
            'vocabulary' => $this->curriculum->vocabulary(),
            'canEdit' => auth()->user()->can('update', $this->pathway),
            'canTransition' => auth()->user()->can('transition', $this->pathway),
        ]);
    }
}
