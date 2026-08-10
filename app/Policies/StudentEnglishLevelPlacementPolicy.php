<?php

namespace App\Policies;

use App\Models\StudentEnglishLevelPlacement;
use App\Models\User;

/**
 * Proficiency placements name individual students, so they follow the same
 * rule as teaching groups: `academics.manage` only, no teacher access until
 * Step 2b provides a teaching assignment to scope it through.
 */
class StudentEnglishLevelPlacementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('academics.manage');
    }

    public function view(User $user, StudentEnglishLevelPlacement $placement): bool
    {
        return $user->can('academics.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('academics.manage');
    }

    public function update(User $user, StudentEnglishLevelPlacement $placement): bool
    {
        return $user->can('academics.manage');
    }

    public function delete(User $user, StudentEnglishLevelPlacement $placement): bool
    {
        return $user->can('academics.manage');
    }
}
