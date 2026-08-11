<?php

namespace App\Policies;

use App\Models\LearningObjective;
use App\Models\User;

/**
 * The canonical objective library is school-level academic content:
 * `academics.view` reads it, `academics.manage` authors it.
 *
 * Teachers stay read-only for now. Their collaborative work belongs in ATP,
 * which is per-phase and explicitly cross-grade; widening this permission
 * ahead of that design would be guessing at a workflow nobody has specified.
 */
class LearningObjectivePolicy
{
    public function view(User $user, LearningObjective $objective): bool
    {
        return $user->can('academics.view');
    }

    public function create(User $user): bool
    {
        return $user->can('academics.manage');
    }

    /**
     * Editing is draft-only. The model enforces that independently of role --
     * an active objective is frozen for a principal too, because this is
     * versioning rather than a permission question.
     */
    public function update(User $user, LearningObjective $objective): bool
    {
        return $user->can('academics.manage') && $objective->isDraft();
    }

    /** Activation and archiving are lifecycle actions, not content edits. */
    public function transition(User $user, LearningObjective $objective): bool
    {
        return $user->can('academics.manage') && ! $objective->isArchived();
    }

    public function delete(User $user, LearningObjective $objective): bool
    {
        return $this->update($user, $objective);
    }
}
