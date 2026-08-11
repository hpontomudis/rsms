<?php

namespace App\Livewire\LearningPhases;

use App\Models\LearningPhase;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The national phase structure, visible so academic managers can see what
 * curriculum work will hang off. Read-only apart from a phase's description
 * and status -- codes, sequences and grade mappings are national structure.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    public ?int $editingId = null;

    public string $description = '';

    public string $status = '';

    public function mount(): void
    {
        $this->authorize('viewAny', LearningPhase::class);
    }

    public function startEditing(int $phaseId): void
    {
        $phase = LearningPhase::findOrFail($phaseId);
        $this->authorize('update', $phase);

        $this->editingId = $phase->id;
        $this->description = $phase->description ?? '';
        $this->status = $phase->status;
        $this->resetErrorBag();
    }

    public function cancelEditing(): void
    {
        $this->reset(['editingId', 'description', 'status']);
    }

    public function save(): void
    {
        $phase = LearningPhase::findOrFail($this->editingId);
        $this->authorize('update', $phase);

        $validated = $this->validate([
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:active,archived'],
        ]);

        $phase->update([
            'description' => $validated['description'] !== '' ? $validated['description'] : null,
            'status' => $validated['status'],
        ]);

        $this->reset(['editingId', 'description', 'status']);
    }

    public function render()
    {
        return view('livewire.learning-phases.index', [
            'phases' => LearningPhase::with(['gradeLinks.grade'])->orderBy('sequence')->get(),
        ]);
    }
}
