<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\User;

class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('attendance.view');
    }

    public function view(User $user, Attendance $attendance): bool
    {
        if (! $user->can('attendance.view')) {
            return false;
        }

        return $this->hasClassAccess($user, $attendance->schoolClass);
    }

    /**
     * Whether the user may open the attendance-entry screen for this class
     * at all (a teacher only sees classes they teach).
     */
    public function recordFor(User $user, SchoolClass $schoolClass): bool
    {
        if (! $user->can('attendance.record')) {
            return false;
        }

        return $this->hasClassAccess($user, $schoolClass);
    }

    /**
     * Whether the user may view attendance reports/history for this class
     * (a teacher only sees classes they teach; view-only roles see all).
     */
    public function viewFor(User $user, SchoolClass $schoolClass): bool
    {
        if (! $user->can('attendance.view')) {
            return false;
        }

        return $this->hasClassAccess($user, $schoolClass);
    }

    /**
     * Whether an already-taken session may still be edited. Teachers get a
     * same-day edit window per PRD §9.8; admin/super_admin can correct
     * historical records.
     */
    public function update(User $user, Attendance $attendance): bool
    {
        if (! $this->recordFor($user, $attendance->schoolClass)) {
            return false;
        }

        if ($user->hasRole('teacher') && ! $attendance->isEditableToday()) {
            return false;
        }

        return true;
    }

    private function hasClassAccess(User $user, SchoolClass $schoolClass): bool
    {
        if (! $user->hasRole('teacher')) {
            return true;
        }

        $staffId = $user->staff?->id;

        return $staffId && $schoolClass->teachers()->where('staff_id', $staffId)->exists();
    }
}
