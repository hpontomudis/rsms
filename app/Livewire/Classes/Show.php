<?php

namespace App\Livewire\Classes;

use App\Models\ClassTeacher;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public SchoolClass $schoolClass;

    public bool $showAssignTeacher = false;

    public string $staff_id = '';

    public string $role = '';

    public bool $showEnrollStudent = false;

    public string $student_id = '';

    public string $enrolled_at = '';

    public function mount(SchoolClass $schoolClass): void
    {
        $this->authorize('view', $schoolClass);
        $this->schoolClass = $schoolClass;
        $this->enrolled_at = now()->toDateString();
    }

    public function assignTeacher(): void
    {
        $this->authorize('update', $this->schoolClass);

        $validated = $this->validate([
            'staff_id' => ['required', 'exists:staff,id'],
            'role' => ['required', Rule::in(['homeroom', 'assistant', 'subject_teacher'])],
        ]);

        $this->schoolClass->teachers()->syncWithoutDetaching([
            $validated['staff_id'] => ['role' => $validated['role']],
        ]);

        $this->reset(['staff_id', 'role', 'showAssignTeacher']);
        $this->schoolClass->refresh();
    }

    public function removeTeacher(int $staffId, string $role): void
    {
        $this->authorize('update', $this->schoolClass);
        ClassTeacher::where('class_id', $this->schoolClass->id)
            ->where('staff_id', $staffId)
            ->where('role', $role)
            ->delete();
        $this->schoolClass->refresh();
    }

    public function enrollStudent(): void
    {
        $this->authorize('update', $this->schoolClass);

        $validated = $this->validate([
            'student_id' => ['required', 'exists:students,id'],
            'enrolled_at' => ['required', 'date'],
        ]);

        $this->schoolClass->students()->syncWithoutDetaching([
            $validated['student_id'] => [
                'enrolled_at' => $validated['enrolled_at'],
                'status' => 'active',
            ],
        ]);

        $this->reset(['student_id', 'showEnrollStudent']);
        $this->schoolClass->refresh();
    }

    public function unenrollStudent(int $studentId): void
    {
        $this->authorize('update', $this->schoolClass);
        $this->schoolClass->students()->detach($studentId);
        $this->schoolClass->refresh();
    }

    public function render()
    {
        return view('livewire.classes.show', [
            'availableStaff' => Staff::where('status', 'active')->orderBy('first_name')->get(),
            'availableStudents' => Student::where('status', 'active')->orderBy('first_name')->get(),
        ]);
    }
}
