<?php

namespace App\Services;

use App\Models\ClassStudent;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The one write path for `class_student` (Foundation F3). Every write goes
 * through a direct model call (`ClassStudent::create()`/`update()`/
 * `delete()`), never the BelongsToMany `attach()`/`detach()`/`sync()`
 * helpers -- see `ClassStudent`'s docblock for why that distinction is
 * load-bearing for the Auditable trail (the same reasoning already applied
 * to `ClassTeacherService` in Foundation F2).
 *
 * Boundary convention: half-open `[enrolled_at, ended_on)`. See
 * `ClassStudent`'s docblock and the migration for why this differs from
 * `class_subject`/`teaching_group_student`'s closed interval.
 */
class ClassStudentService
{
    /**
     * Enroll a Student with no current class into one. Refuses -- rather
     * than silently creating a second open row -- if the Student already
     * has an open enrollment elsewhere; the caller must use transfer()
     * instead. Enrolling into the Student's own already-current Class is an
     * idempotent no-op.
     */
    public function enroll(SchoolClass $class, Student $student, ?Carbon $on = null): ClassStudent
    {
        $on ??= Carbon::today();

        return DB::transaction(function () use ($class, $student, $on) {
            $current = ClassStudent::where('student_id', $student->id)->open()->lockForUpdate()->first();

            if ($current && $current->class_id === $class->id) {
                return $current;
            }

            if ($current) {
                $this->fail("{$student->fullName()} already has a current class ({$current->schoolClass->name}). Use Transfer instead.");
            }

            $this->assertWithinAcademicYear($class, $on);
            $this->assertNoOverlap($student, $on);

            return ClassStudent::create([
                'class_id' => $class->id,
                'student_id' => $student->id,
                'enrolled_at' => $on,
                'status' => 'active',
            ]);
        });
    }

    /**
     * Transfer a Student's current enrollment to a different Class.
     * Transactional close-and-create on the SAME effective date: the
     * outgoing row closes (`status=transferred_out`, `ended_on` = the
     * effective date) and the incoming row opens (`enrolled_at` = the same
     * date) -- non-overlapping under the half-open boundary convention.
     * Transferring to the Student's own already-current Class is an
     * idempotent no-op. Refuses if the Student has no current enrollment to
     * transfer from -- use enroll() for a Student's first class.
     */
    public function transfer(Student $student, SchoolClass $toClass, ?Carbon $on = null): ClassStudent
    {
        $on ??= Carbon::today();

        return DB::transaction(function () use ($student, $toClass, $on) {
            $current = ClassStudent::where('student_id', $student->id)->open()->lockForUpdate()->first();

            if (! $current) {
                $this->fail("{$student->fullName()} has no current class to transfer from. Use Enroll instead.");
            }

            if ($current->class_id === $toClass->id) {
                return $current;
            }

            if ($on->lte($current->enrolled_at)) {
                $this->fail('The transfer date must be after the current enrollment\'s start date ('.$current->enrolled_at->toDateString().').');
            }

            $this->assertWithinAcademicYear($toClass, $on);
            $this->assertNoOverlap($student, $on, ignoreId: $current->id);

            $current->update(['status' => 'transferred_out', 'ended_on' => $on]);

            return ClassStudent::create([
                'class_id' => $toClass->id,
                'student_id' => $student->id,
                'enrolled_at' => $on,
                'status' => 'active',
            ]);
        });
    }

    /**
     * Leave the current Class without opening another -- e.g. the Student
     * is leaving the school. Closes the current row; opens no successor.
     * History preserved, never hard-deleted.
     */
    public function withdraw(Student $student, ?Carbon $on = null): ClassStudent
    {
        $on ??= Carbon::today();

        $current = ClassStudent::where('student_id', $student->id)->open()->first();

        if (! $current) {
            $this->fail("{$student->fullName()} has no current class to withdraw from.");
        }

        if ($on->lte($current->enrolled_at)) {
            $this->fail('The withdrawal date must be after the enrollment\'s start date ('.$current->enrolled_at->toDateString().').');
        }

        $current->update(['status' => 'withdrawn', 'ended_on' => $on]);

        return $current;
    }

    /**
     * A tightly bounded same-day data-entry correction: the Student's
     * current enrollment was created TODAY and is simply wrong. Hard-deletes
     * that row (audited via the `deleted` Auditable event) and enrolls
     * fresh into the correct Class -- never available once the row is
     * older than today, which is treated as settled history requiring
     * transfer() instead. Deliberately narrow rather than a general
     * "is anything depending on this row" check (Attendance, Assessment
     * results, etc.), per the architecture review's explicit preference for
     * a bounded operation over a dependency-detection engine.
     */
    public function correctCurrentEnrollment(Student $student, SchoolClass $correctClass): ClassStudent
    {
        $current = ClassStudent::where('student_id', $student->id)->open()->first();

        if (! $current) {
            $this->fail("{$student->fullName()} has no current class to correct.");
        }

        if (! Carbon::today()->isSameDay($current->enrolled_at)) {
            $this->fail('Only a same-day enrollment may be corrected this way. Use Transfer for a settled enrollment.');
        }

        if ($current->class_id === $correctClass->id) {
            return $current;
        }

        return DB::transaction(function () use ($current, $correctClass, $student) {
            $current->delete();

            return ClassStudent::create([
                'class_id' => $correctClass->id,
                'student_id' => $student->id,
                'enrolled_at' => Carbon::today(),
                'status' => 'active',
            ]);
        });
    }

    /**
     * Any of the Student's OTHER enrollment rows (open or closed) whose
     * [enrolled_at, ended_on) window overlaps the proposed one. Public so
     * the rule can be tested directly, including closed-versus-closed cases
     * no UI path can produce -- the same shape as
     * `EnglishPlacementService::overlappingPlacement()`.
     */
    public function overlappingEnrollment(Student $student, Carbon $startedOn, ?Carbon $endedOn = null, ?int $ignoreId = null): ?ClassStudent
    {
        $query = ClassStudent::where('student_id', $student->id)
            ->where(fn ($q) => $q->whereNull('ended_on')->orWhereDate('ended_on', '>', $startedOn));

        if ($endedOn) {
            $query->whereDate('enrolled_at', '<', $endedOn);
        }

        if ($ignoreId) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->with('schoolClass')->first();
    }

    private function assertNoOverlap(Student $student, Carbon $startedOn, ?int $ignoreId = null): void
    {
        $clash = $this->overlappingEnrollment($student, $startedOn, null, $ignoreId);

        if (! $clash) {
            return;
        }

        $until = $clash->ended_on?->toDateString() ?? 'now';

        $this->fail("{$student->fullName()} already has a class enrollment ({$clash->schoolClass->name}) covering {$startedOn->toDateString()}, from {$clash->enrolled_at->toDateString()} to {$until}. Enrollment periods cannot overlap.");
    }

    /**
     * Enrollment dates must sit inside the Class's academic year --
     * academic_years.start_date/end_date are both NOT NULL, so this is a
     * real boundary, not an invented one. Same rule
     * TeachingGroupMembershipService applies.
     */
    private function assertWithinAcademicYear(SchoolClass $class, Carbon $date): void
    {
        $year = $class->academicYear;

        if (! $year) {
            return;
        }

        if ($date->lt($year->start_date) || $date->gt($year->end_date)) {
            $this->fail("The date must fall within {$year->name} ({$year->start_date->toDateString()} to {$year->end_date->toDateString()}).");
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['class_student' => $message]);
    }
}
