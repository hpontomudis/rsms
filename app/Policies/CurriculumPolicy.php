<?php

namespace App\Policies;

use App\Models\Curriculum;
use App\Models\User;

class CurriculumPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('academics.view');
    }

    public function view(User $user, Curriculum $curriculum): bool
    {
        return $user->can('academics.view');
    }

    public function create(User $user): bool
    {
        return $user->can('academics.manage');
    }

    /**
     * Covers editing permitted metadata, activating and archiving. What may
     * actually change is enforced by the model: a version's identity is
     * immutable once it leaves draft.
     */
    public function update(User $user, Curriculum $curriculum): bool
    {
        return $user->can('academics.manage');
    }
}
