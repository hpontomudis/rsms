<?php

namespace App\Policies;

use App\Models\EnglishProgramme;
use App\Models\User;

/**
 * English programmes are school-wide academic standards, so they reuse the
 * existing academics permissions rather than introducing a parallel English
 * permission domain. No per-teacher scoping: unlike a teaching assignment,
 * a programme belongs to nobody in particular.
 */
class EnglishProgrammePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('academics.view');
    }

    public function view(User $user, EnglishProgramme $programme): bool
    {
        return $user->can('academics.view');
    }

    public function create(User $user): bool
    {
        return $user->can('academics.manage');
    }

    public function update(User $user, EnglishProgramme $programme): bool
    {
        return $user->can('academics.manage');
    }

    /**
     * Covers archiving and managing the programme's levels and grade
     * applicability -- all are edits to the same standard.
     */
    public function delete(User $user, EnglishProgramme $programme): bool
    {
        return $user->can('academics.manage');
    }
}
