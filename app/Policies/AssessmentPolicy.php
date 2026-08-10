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

        return $this->hasClassSubjectAccess($user, $classSubject, requireActive: true);
    }

    /**
     * Whether scores may be entered/edited for this assessment.
     */
    public function recordScores(User $user, Assessment $assessment): bool
    {
        if (! $user->can('academics.record')) {
            return false;
        }

        return $this->hasClassSubjectAccess($user, $assessment->classSubject, requireActive: true);
    }

    /**
     * Teachers are scoped to their own assignment; everyone else holding the
     * permission (admin, principal) is not.
     *
     * `$requireActive` is the read/write split introduced with effective-dated
     * assignments: a teacher keeps READ access to work recorded under an
     * assignment that has since been handed over, but can no longer WRITE to
     * it. Admins retain write access for corrections.
     */
    private function hasClassSubjectAccess(User $user, ClassSubject $classSubject, bool $requireActive = false): bool
    {
        if (! $user->hasRole('teacher')) {
            return true;
        }

        $staffId = $user->staff?->id;

        if (! $staffId || $classSubject->staff_id !== $staffId) {
            return false;
        }

        return $requireActive ? $classSubject->isActive() : true;
    }
}
