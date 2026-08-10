<?php

namespace App\Policies;

use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Models\User;

class AssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('academics.view');
    }

    public function view(User $user, Assessment $assessment): bool
    {
        if (! $user->can('academics.view')) {
            return false;
        }

        return $this->hasClassSubjectAccess($user, $assessment->classSubject);
    }

    /**
     * Whether the user may view the list of assessments for this
     * class-subject assignment.
     */
    public function viewFor(User $user, ClassSubject $classSubject): bool
    {
        if (! $user->can('academics.view')) {
            return false;
        }

        return $this->hasClassSubjectAccess($user, $classSubject);
    }

    /**
     * Whether the user may create an assessment for this class-subject
     * assignment (a teacher only sees the subjects they've been assigned
     * to teach).
     */
    public function createFor(User $user, ClassSubject $classSubject): bool
    {
        if (! $user->can('academics.record')) {
            return false;
        }

        // A closed assignment is history: no NEW academic activity may be
        // recorded against it by anyone, admins included. Backfilling a
        // missed assessment would need its own explicit, audited workflow.
        if (! $classSubject->isActive()) {
            return false;
        }

        return $this->hasClassSubjectAccess($user, $classSubject);
    }

    /**
     * Whether scores may be entered/edited on an assessment that already exists.
     */
    public function recordScores(User $user, Assessment $assessment): bool
    {
        if (! $user->can('academics.record')) {
            return false;
        }

        $classSubject = $assessment->classSubject;

        if (! $this->hasClassSubjectAccess($user, $classSubject)) {
            return false;
        }

        // Teachers are frozen out once their assignment is handed over;
        // admins/principals may still correct existing historical records.
        if ($user->hasRole('teacher') && ! $classSubject->isActive()) {
            return false;
        }

        return true;
    }

    /**
     * Teachers are scoped to their own assignment; everyone else holding the
     * relevant permission (admin, principal) is not.
     *
     * Deliberately says nothing about active vs. closed -- that rule differs
     * per action and is applied by the callers above.
     */
    private function hasClassSubjectAccess(User $user, ClassSubject $classSubject): bool
    {
        if (! $user->hasRole('teacher')) {
            return true;
        }

        $staffId = $user->staff?->id;

        return $staffId && $classSubject->staff_id === $staffId;
    }
}
