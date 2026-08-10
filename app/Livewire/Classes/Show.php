<?php

namespace App\Livewire\Classes;

use App\Models\ClassSubject;
use App\Models\ClassTeacher;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
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

    public bool $showAssignSubject = false;

    public string $subject_id = '';

    public string $subject_teacher_id = '';

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

    public function assignSubject(): void
    {
        $this->authorize('manageAcademics', $this->schoolClass);

        $validated = $this->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'subject_teacher_id' => ['required', 'exists:staff,id'],
        ]);

        $current = ClassSubject::where('class_id', $this->schoolClass->id)
            ->where('subject_id', $validated['subject_id'])
            ->active()
            ->first();

        // Re-selecting the teacher already in place is a no-op, not a handover.
        if ($current && $current->staff_id === (int) $validated['subject_teacher_id']) {
            $this->reset(['subject_id', 'subject_teacher_id', 'showAssignSubject']);

            return;
        }

        // Close-and-create, never mutate: whatever already points at $current
        // (assessments today, planning records later) must keep resolving to
        // the teacher who actually held the assignment at the time.
        DB::transaction(function () use ($validated, $current) {
            $current?->update(['ended_on' => today()]);

            ClassSubject::create([
                'class_id' => $this->schoolClass->id,
                'subject_id' => $validated['subject_id'],
                'staff_id' => $validated['subject_teacher_id'],
                'started_on' => today(),
            ]);
        });

        $this->reset(['subject_id', 'subject_teacher_id', 'showAssignSubject']);
        $this->schoolClass->refresh();
    }

    public function removeSubject(int $classSubjectId): void
    {
        $this->authorize('manageAcademics', $this->schoolClass);

        $classSubject = ClassSubject::where('id', $classSubjectId)
            ->where('class_id', $this->schoolClass->id)
            ->active()
            ->first();

        if (! $classSubject) {
            return;
        }

        // With history attached, closing preserves attribution; the FK's
        // restrictOnDelete would block a hard delete anyway. Without history
        // there is nothing to preserve, so remove the row outright.
        if ($classSubject->assessments()->exists()) {
            $classSubject->update(['ended_on' => today()]);
        } else {
            $classSubject->delete();
        }

        $this->schoolClass->refresh();
    }

    public function render()
    {
        return view('livewire.classes.show', [
            'availableStaff' => Staff::where('status', 'active')->orderBy('first_name')->get(),
            'availableStudents' => Student::where('status', 'active')->orderBy('first_name')->get(),
            'availableSubjects' => Subject::where(function ($q) {
                $q->whereNull('grade_id')->orWhere('grade_id', $this->schoolClass->grade_id);
            })->orderBy('name')->get(),
            // Active only -- superseded assignments stay in the database for
            // historical attribution but are not part of the class as it
            // stands today.
            'classSubjects' => $this->schoolClass->classSubjects()
                ->active()
                ->with('subject', 'teacher')
                ->withCount('assessments')
                ->get(),
        ]);
    }
}
