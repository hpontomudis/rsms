<?php

namespace App\Policies;

use App\Models\SemesterProgramme;
use App\Models\User;

/**
 * Semester planning inherits the annual programme's authority: whoever may
 * edit the year may schedule the term. Keeping one rule avoids the two layers
 * drifting apart on who is responsible.
 */
class SemesterProgrammePolicy
{
    public function view(User $user, SemesterProgramme $programme): bool
    {
        return $user->can('academics.view');
    }

    public function update(User $user, SemesterProgramme $programme): bool
    {
        if ($programme->isArchived()) {
            return false;
        }

        return $user->can('update', $programme->annualProgramme);
    }

    public function delete(User $user, SemesterProgramme $programme): bool
    {
        return $programme->isDraft() && $this->update($user, $programme);
    }

    public function transition(User $user, SemesterProgramme $programme): bool
    {
        return $user->can('academics.manage') && ! $programme->isArchived();
    }
}
