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
     * A teacher may only see students enrolled in a class they teach.
     */
    private function teaches(User $user, Student $student): bool
    {
        $staffId = $user->staff?->id;

        if (! $staffId) {
            return false;
        }

        return $student->classes()
            ->whereHas('teachers', fn ($q) => $q->where('staff_id', $staffId))
            ->exists();
    }
}
