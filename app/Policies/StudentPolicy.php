<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('students.view');
    }

    public function view(User $user, Student $student): bool
    {
        if (! $user->can('students.view')) {
            return false;
        }

        if ($user->hasRole('teacher')) {
            return $this->teaches($user, $student);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('students.create');
    }

    public function update(User $user, Student $student): bool
    {
        return $user->can('students.update');
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->can('students.delete');
    }

    /**
     * A teacher may only see students CURRENTLY enrolled in a class they
     * CURRENTLY teach (homeroom/assistant `class_teacher`) -- deliberately
     * narrower than `TeacherAudienceScope`'s authority (which also counts
     * `class_subject`/`TeachingGroup`); see `AcademicRecordPolicy`'s and
     * `CommunicationPolicy`'s docblocks, which already document that
     * StudentPolicy is NOT widened to match Communication's broader
     * definition -- reusing it here would silently change what an issued
     * Academic Record's read gate allows too.
     *
     * Two independent effective-dating checks, both load-bearing
     * (Foundation F3.1): the Student's OWN `class_student` row must be
     * OPEN (a transferred-out/withdrawn enrollment is history, not current
     * membership), and the Staff's `class_teacher` row on that class must
     * also be OPEN (a closed/handed-over assignment grants nothing). Either
     * one alone would leave a historical-authority leak.
     */
    private function teaches(User $user, Student $student): bool
    {
        $staffId = $user->staff?->id;

        if (! $staffId) {
            return false;
        }

        return $student->classes()
            ->wherePivotNull('ended_on')
            ->whereHas('teachers', fn ($q) => $q->where('staff_id', $staffId)->whereNull('class_teacher.ended_on'))
            ->exists();
    }
}
