<?php

namespace App\Policies;

use App\Models\LearningPhase;
use App\Models\User;

/**
 * Learning phases are school-wide academic reference data, readable by anyone
 * who can see academics and editable only by academic management. They name no
 * students, so reading them is safe for teachers -- unlike a group roster.
 */
class LearningPhasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('academics.view');
    }

    public function view(User $user, LearningPhase $phase): bool
    {
        return $user->can('academics.view');
    }

    /**
     * Covers editing a phase's description and status. Codes, sequences and
     * grade mappings are deliberately not editable through the UI in this
     * step -- they are national structure, and changing them once curriculum
     * data depends on them would rewrite what that data covered.
     */
    public function update(User $user, LearningPhase $phase): bool
    {
        return $user->can('academics.manage');
    }
}
