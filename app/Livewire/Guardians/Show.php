<?php

namespace App\Livewire\Guardians;

use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Guardian $guardian;

    public bool $showAttachStudent = false;

    public string $student_id = '';

    public string $relationship_type = '';

    public bool $is_primary_contact = false;

    public bool $can_pickup = true;

    public function mount(Guardian $guardian): void
    {
        $this->authorize('view', $guardian);
        $this->guardian = $guardian;
    }

    public function attachStudent(): void
    {
        $this->authorize('update', $this->guardian);

        $validated = $this->validate([
            'student_id' => ['required', 'exists:students,id'],
            'relationship_type' => ['required', Rule::in(['father', 'mother', 'grandparent', 'sibling', 'legal_guardian', 'other'])],
            'is_primary_contact' => ['boolean'],
            'can_pickup' => ['boolean'],
        ]);

        $this->guardian->students()->syncWithoutDetaching([
            $validated['student_id'] => [
                'relationship_type' => $validated['relationship_type'],
                'is_primary_contact' => $validated['is_primary_contact'],
                'can_pickup' => $validated['can_pickup'],
            ],
        ]);

        $this->reset(['student_id', 'relationship_type', 'is_primary_contact', 'showAttachStudent']);
        $this->can_pickup = true;
        $this->guardian->refresh();
    }

    public function detachStudent(int $studentId): void
    {
        $this->authorize('update', $this->guardian);
        $this->guardian->students()->detach($studentId);
        $this->guardian->refresh();
    }

    public function archive()
    {
        $this->authorize('delete', $this->guardian);
        $this->guardian->delete();

        session()->flash('status', "{$this->guardian->fullName()} was archived.");

        return $this->redirect(route('guardians.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.guardians.show', [
            'availableStudents' => Student::orderBy('first_name')->get(),
        ]);
    }
}
