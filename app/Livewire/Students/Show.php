<?php

namespace App\Livewire\Students;

use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Student $student;

    public bool $showAttachGuardian = false;

    public string $guardian_id = '';

    public string $relationship_type = '';

    public bool $is_primary_contact = false;

    public bool $can_pickup = true;

    public bool $showEnroll = false;

    public string $class_id = '';

    public string $enrolled_at = '';

    public function mount(Student $student): void
    {
        $this->authorize('view', $student);
        $this->student = $student;
        $this->enrolled_at = now()->toDateString();
    }

    public function attachGuardian(): void
    {
        $this->authorize('update', $this->student);

        $validated = $this->validate([
            'guardian_id' => ['required', 'exists:guardians,id'],
            'relationship_type' => ['required', Rule::in(['father', 'mother', 'grandparent', 'sibling', 'legal_guardian', 'other'])],
            'is_primary_contact' => ['boolean'],
            'can_pickup' => ['boolean'],
        ]);

        $this->student->guardians()->syncWithoutDetaching([
            $validated['guardian_id'] => [
                'relationship_type' => $validated['relationship_type'],
                'is_primary_contact' => $validated['is_primary_contact'],
                'can_pickup' => $validated['can_pickup'],
            ],
        ]);

        $this->reset(['guardian_id', 'relationship_type', 'is_primary_contact', 'showAttachGuardian']);
        $this->can_pickup = true;
        $this->student->refresh();
    }

    public function detachGuardian(int $guardianId): void
    {
        $this->authorize('update', $this->student);
        $this->student->guardians()->detach($guardianId);
        $this->student->refresh();
    }

    public function enrollInClass(): void
    {
        $this->authorize('update', $this->student);

        $validated = $this->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'enrolled_at' => ['required', 'date'],
        ]);

        $this->student->classes()->syncWithoutDetaching([
            $validated['class_id'] => [
                'enrolled_at' => $validated['enrolled_at'],
                'status' => 'active',
            ],
        ]);

        $this->reset(['class_id', 'showEnroll']);
        $this->student->refresh();
    }

    public function archive()
    {
        $this->authorize('delete', $this->student);
        $this->student->delete();

        session()->flash('status', "{$this->student->fullName()} was archived.");

        return $this->redirect(route('students.index'), navigate: true);
    }

    public function render()
    {
        $canViewAttendance = auth()->user()->can('attendance.view');

        return view('livewire.students.show', [
            'availableGuardians' => Guardian::orderBy('first_name')->get(),
            'availableClasses' => SchoolClass::whereHas('academicYear', fn ($q) => $q->where('is_current', true))
                ->with('grade')
                ->get(),
            'currentClass' => $this->student->currentClass(),
            'canViewAttendance' => $canViewAttendance,
            'recentAttendance' => $canViewAttendance
                ? $this->student->attendanceRecords()
                    ->with('attendance.schoolClass')
                    ->whereHas('attendance')
                    ->get()
                    ->sortByDesc(fn ($record) => $record->attendance->date)
                    ->take(10)
                : collect(),
        ]);
    }
}
