<?php

namespace App\Policies;

use App\Models\Guardian;
use App\Models\User;

class GuardianPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('guardians.view');
    }

    public function view(User $user, Guardian $guardian): bool
    {
        return $user->can('guardians.view');
    }

    public function create(User $user): bool
    {
        return $user->can('guardians.create');
    }

    public function update(User $user, Guardian $guardian): bool
    {
        return $user->can('guardians.update');
    }

    public function delete(User $user, Guardian $guardian): bool
    {
        return $user->can('guardians.delete');
    }
}
