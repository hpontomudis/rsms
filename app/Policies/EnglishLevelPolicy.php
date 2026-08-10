<?php

namespace App\Policies;

use App\Models\EnglishLevel;
use App\Models\User;

class EnglishLevelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('academics.view');
    }

    public function view(User $user, EnglishLevel $level): bool
    {
        return $user->can('academics.view');
    }

    public function create(User $user): bool
    {
        return $user->can('academics.manage');
    }

    public function update(User $user, EnglishLevel $level): bool
    {
        return $user->can('academics.manage');
    }

    public function delete(User $user, EnglishLevel $level): bool
    {
        return $user->can('academics.manage');
    }
}
