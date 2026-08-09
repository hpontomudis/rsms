<?php

namespace App\Policies;

use App\Models\SchoolClass;
use App\Models\User;

class SchoolClassPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('classes.view');
    }

    public function view(User $user, SchoolClass $schoolClass): bool
    {
        if (! $user->can('classes.view')) {
            return false;
        }

        if ($user->hasRole('teacher')) {
            $staffId = $user->staff?->id;

            return $staffId && $schoolClass->teachers()->where('staff_id', $staffId)->exists();
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('classes.create');
    }

    public function update(User $user, SchoolClass $schoolClass): bool
    {
        return $user->can('classes.update');
    }

    public function delete(User $user, SchoolClass $schoolClass): bool
    {
        return $user->can('classes.delete');
    }

    /**
     * Assigning a subject + teacher to this class is an admin action, not
     * something a teacher can self-serve.
     */
    public function manageAcademics(User $user, SchoolClass $schoolClass): bool
    {
        return $user->can('academics.manage');
    }
}
