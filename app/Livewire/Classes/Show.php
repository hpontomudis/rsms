<?php

namespace App\Livewire\Classes;

use App\Models\ClassSubject;
use App\Models\ClassTeacher;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Services\ClassStudentService;
use App\Services\ClassTeacherService;
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

    /** Non-empty while the transfer-destination form is open for this student id. */
    public string $transferringStudentId = '';

    public string $transfer_class_id = '';

    public string $transfer_on = '';

    public bool $showAssignSubject = false;

    public string $subject_id = '';

    public string $subject_teacher_id = '';

    public function mount(SchoolClass $schoolClass): void
    {
        $this->authorize('view', $schoolClass);
        $this->schoolClass = $schoolClass;
        $this->enrolled_at = now()->toDateString();
    }

    /**
     * `role` is deliberately limited to homeroom/assistant -- subject_teacher
     * is deprecated for new writes (Foundation F2; ClassSubject is canonical
     * for subject teaching). Both roles go through ClassTeacherService, never
     * a direct pivot attach: for homeroom, setHomeroom() transparently
     * performs a transactional handover if one is already assigned; for
     * assistant, assignAssistant() is a plain effective-dated add.
     */
    public function assignTeacher(ClassTeacherService $service): void
    {
        $this->authorize('update', $this->schoolClass);

        $validated = $this->validate([
            'staff_id' => ['required', 'exists:staff,id'],
            'role' => ['required', Rule::in(['homeroom', 'assistant'])],
        ]);

        $staff = Staff::findOrFail($validated['staff_id']);

        if ($validated['role'] === 'homeroom') {
            $service->setHomeroom($this->schoolClass, $staff);
        } else {
            $service->assignAssistant($this->schoolClass, $staff);
        }

        $this->reset(['staff_id', 'role', 'showAssignTeacher']);
        $this->schoolClass->refresh();
    }

    /**
     * Closes the assignment (ended_on = today) rather than deleting it --
     * history is preserved. For homeroom, this is the "temporarily no
     * homeroom teacher" case (§14): no successor is opened.
     */
    public function removeTeacher(int $staffId, string $role, ClassTeacherService $service): void
    {
        $this->authorize('update', $this->schoolClass);

        $assignment = ClassTeacher::where('class_id', $this->schoolClass->id)
            ->where('staff_id', $staffId)
            ->where('role', $role)
            ->open()
            ->first();

        if ($assignment) {
            $service->endAssignment($assignment);
        }

        $this->schoolClass->refresh();
    }

    /**
     * Foundation F3: enrollment goes through ClassStudentService, the one
     * write path. If the Student already has a current class elsewhere,
     * the service refuses (never a silent second open row) with a message
     * directing to Transfer -- surfaced here via the same validation-error
     * bag a form-level failure would use.
     */
    public function enrollStudent(ClassStudentService $service): void
    {
        $this->authorize('update', $this->schoolClass);

        $validated = $this->validate([
            'student_id' => ['required', 'exists:students,id'],
            'enrolled_at' => ['required', 'date'],
        ]);

        $service->enroll(
            $this->schoolClass,
            Student::findOrFail($validated['student_id']),
            \Illuminate\Support\Carbon::parse($validated['enrolled_at']),
        );

        $this->reset(['student_id', 'showEnrollStudent']);
        $this->schoolClass->refresh();
    }

    public function showTransferForm(int $studentId): void
    {
        $this->transferringStudentId = (string) $studentId;
        $this->transfer_class_id = '';
        $this->transfer_on = now()->toDateString();
    }

    /**
     * Close-and-create to a different administrative Class, in one
     * transaction. See ClassStudentService::transfer().
     */
    public function transferStudent(ClassStudentService $service): void
    {
        $this->authorize('update', $this->schoolClass);

        $validated = $this->validate([
            'transfer_class_id' => ['required', 'exists:classes,id', 'different:schoolClass.id'],
            'transfer_on' => ['required', 'date'],
        ]);

        $service->transfer(
            Student::findOrFail($this->transferringStudentId),
            SchoolClass::findOrFail($validated['transfer_class_id']),
            \Illuminate\Support\Carbon::parse($validated['transfer_on']),
        );

        $this->reset(['transferringStudentId', 'transfer_class_id', 'transfer_on']);
        $this->schoolClass->refresh();
    }

    /**
     * Leaving the school entirely, not moving classes -- closes the
     * enrollment with no successor. History preserved, never a hard
     * detach.
     */
    public function withdrawStudent(int $studentId, ClassStudentService $service): void
    {
        $this->authorize('update', $this->schoolClass);
        $service->withdraw(Student::findOrFail($studentId));
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
            // Current teachers only -- a closed (historical) assignment is
            // no longer part of the class as it stands today, the same
            // "active only" rule already applied to classSubjects below.
            'currentTeachers' => $this->schoolClass->teachers()->wherePivotNull('ended_on')->get(),
            'availableStaff' => Staff::where('status', 'active')->orderBy('first_name')->get(),
            'availableStudents' => Student::where('status', 'active')->orderBy('first_name')->get(),
            // Current roster only -- a closed (historical) enrollment is no
            // longer part of the class as it stands today (Foundation F3).
            'currentStudents' => $this->schoolClass->students()->wherePivotNull('ended_on')->orderBy('first_name')->get(),
            'transferTargetClasses' => SchoolClass::where('id', '!=', $this->schoolClass->id)
                ->where('academic_year_id', $this->schoolClass->academic_year_id)
                ->orderBy('name')
                ->get(),
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
