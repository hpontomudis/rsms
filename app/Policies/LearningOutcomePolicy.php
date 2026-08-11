<?php

namespace App\Policies;

use App\Models\LearningOutcome;
use App\Models\User;

class LearningOutcomePolicy
{
    public function view(User $user, LearningOutcome $outcome): bool
    {
        return $user->can('academics.view');
    }

    public function create(User $user): bool
    {
        return $user->can('academics.manage');
    }

    /**
     * Editable only while the curriculum version is still a draft. Once
     * activated the outcome is a published standard; the answer to a change is
     * a new curriculum version, for managers as much as for anyone else.
     */
    public function update(User $user, LearningOutcome $outcome): bool
    {
        return $user->can('academics.manage') && $outcome->curriculumScope->curriculum->isDraft();
    }

    public function delete(User $user, LearningOutcome $outcome): bool
    {
        return $this->update($user, $outcome);
    }
}
