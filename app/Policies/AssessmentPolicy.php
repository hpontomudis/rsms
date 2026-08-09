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

        return $this->hasClassSubjectAccess($user, $classSubject);
    }

    /**
     * Whether scores may be entered/edited for this assessment.
     */
    public function recordScores(User $user, Assessment $assessment): bool
    {
        if (! $user->can('academics.record')) {
            return false;
        }

        return $this->hasClassSubjectAccess($user, $assessment->classSubject);
    }

    private function hasClassSubjectAccess(User $user, ClassSubject $classSubject): bool
    {
        if (! $user->hasRole('teacher')) {
            return true;
        }

        $staffId = $user->staff?->id;

        return $staffId && $classSubject->staff_id === $staffId;
    }
}
