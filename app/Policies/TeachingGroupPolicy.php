<?php

namespace App\Policies;

use App\Models\TeachingGroup;
use App\Models\User;

/**
 * Teaching groups are gated on `academics.manage`, NOT `academics.view`.
 *
 * That is deliberate and is the whole reason this policy looks stricter than
 * EnglishProgrammePolicy. A group roster is a list of named students; teachers
 * hold `academics.view`, so gating reads on it would hand every teacher the
 * roster of every group in the school. Until Step 2b introduces teaching
 * assignments there is nothing that records WHICH teacher teaches a group, so
 * there is no basis on which to scope a teacher's access -- and the safe
 * answer to "no basis" is no access, not broad access.
 *
 * Reference data from Step 2a-i (programmes, levels) stays readable to
 * teachers; it names no students.
 */
class TeachingGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('academics.manage');
    }

    public function view(User $user, TeachingGroup $group): bool
    {
        return $user->can('academics.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('academics.manage');
    }

    public function update(User $user, TeachingGroup $group): bool
    {
        return $user->can('academics.manage');
    }

    /**
     * Covers archiving and managing the group's roster. Groups are archived,
     * never deleted, once they carry membership history.
     */
    public function delete(User $user, TeachingGroup $group): bool
    {
        return $user->can('academics.manage');
    }
}
