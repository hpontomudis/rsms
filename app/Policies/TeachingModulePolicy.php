<?php

namespace App\Policies;

use App\Models\ClassSubject;
use App\Models\TeachingModule;
use App\Models\User;

/**
 * A module belongs to the teacher who wrote it, through their assignment.
 *
 * This is the deliberate opposite of AnnualProgrammePolicy. A plan for the year
 * belongs to the class and its write access follows whoever currently teaches
 * it; instructional design belongs to its author and does not change hands.
 * Sarah keeps her modules after the handover; Eka reads them and writes her own.
 *
 * The closed-assignment rule is borrowed verbatim from AssessmentPolicy: no NEW
 * work may be recorded against history by anyone, managers included.
 */
class TeachingModulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('academics.view');
    }

    public function view(User $user, TeachingModule $module): bool
    {
        return $user->can('academics.view');
    }

    /** Creating needs the assignment in hand, so the caller passes it. */
    public function createFor(User $user, ClassSubject $assignment): bool
    {
        if (! $user->can('academics.plan')) {
            return false;
        }

        // No new module against closed history -- for a manager either. A plan
        // cannot honestly be written after the teaching it plans.
        if (! $assignment->isActive()) {
            return false;
        }

        return $this->owns($user, $assignment);
    }

    /**
     * Only a draft may be edited at all, and only by its author's assignment
     * (or a manager). A ready module accepts teacher_notes, which the service
     * enforces; the policy question is simply whether this person may touch it.
     */
    public function update(User $user, TeachingModule $module): bool
    {
        if ($module->isArchived()) {
            return false;
        }

        if (! $user->can('academics.plan')) {
            return false;
        }

        $assignment = $module->classSubject;

        if (! $assignment?->isActive()) {
            return false;
        }

        return $this->owns($user, $assignment);
    }

    /** draft <-> ready, and archiving. Same authority as editing. */
    public function transition(User $user, TeachingModule $module): bool
    {
        if ($module->isArchived()) {
            return false;
        }

        if (! $user->can('academics.plan')) {
            return false;
        }

        $assignment = $module->classSubject;

        // Archiving a module whose assignment has closed is still allowed to a
        // manager: it tidies history rather than adding to it.
        if (! $assignment?->isActive()) {
            return $user->can('academics.manage') && $module->isReady();
        }

        return $this->owns($user, $assignment);
    }

    public function delete(User $user, TeachingModule $module): bool
    {
        return $module->isDraft() && $this->update($user, $module);
    }

    /**
     * Teachers are scoped to their own assignment; anyone else holding the
     * permission (admin, principal) is not. Identical in shape to
     * AssessmentPolicy::hasClassSubjectAccess.
     */
    private function owns(User $user, ClassSubject $assignment): bool
    {
        if (! $user->hasRole('teacher')) {
            return true;
        }

        $staffId = $user->staff?->id;

        return $staffId !== null && $assignment->staff_id === $staffId;
    }
}
