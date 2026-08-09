<?php

namespace App\Policies;

use App\Models\Subject;
use App\Models\User;

class SubjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('academics.view');
    }

    public function view(User $user, Subject $subject): bool
    {
        return $user->can('academics.view');
    }

    public function create(User $user): bool
    {
        return $user->can('academics.manage');
    }

    public function update(User $user, Subject $subject): bool
    {
        return $user->can('academics.manage');
    }

    public function delete(User $user, Subject $subject): bool
    {
        return $user->can('academics.manage');
    }
}
