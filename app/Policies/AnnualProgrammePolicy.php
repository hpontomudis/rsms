<?php

namespace App\Policies;

use App\Models\AnnualProgramme;
use App\Models\ClassSubject;
use App\Models\User;

/**
 * Planning authority follows the CURRENT teaching assignment, not the plan.
 *
 * That is the whole point of anchoring the annual programme to the roster: on
 * a handover the plan stays put and the write access moves. Sarah keeps read
 * access to what she wrote; Eka can carry on editing it the day her assignment
 * opens, without recreating anything.
 *
 * No owner_staff_id anywhere -- authorship lives in the audit trail.
 */
class AnnualProgrammePolicy
{
    public function view(User $user, AnnualProgramme $programme): bool
    {
        return $user->can('academics.view');
    }

    /** Creating needs the roster and subject in hand, so the caller passes them. */
    public function createFor(User $user, ?int $classId, ?int $teachingGroupId, int $subjectId): bool
    {
        if ($user->can('academics.manage')) {
            return true;
        }

        return $user->can('academics.plan') && $this->teaches($user, $classId, $teachingGroupId, $subjectId);
    }

    /**
     * Editing an operating plan stays open while it is not archived --
     * a school year shifts, and rebuilding it for every change would be worse
     * than allowing audited edits.
     */
    public function update(User $user, AnnualProgramme $programme): bool
    {
        if ($programme->isArchived()) {
            return false;
        }

        if ($user->can('academics.manage')) {
            return true;
        }

        return $user->can('academics.plan')
            && $this->teaches($user, $programme->class_id, $programme->teaching_group_id, $programme->subject_id);
    }

    public function delete(User $user, AnnualProgramme $programme): bool
    {
        return $programme->isDraft() && $this->update($user, $programme);
    }

    /** Activation and archiving remain management decisions. */
    public function transition(User $user, AnnualProgramme $programme): bool
    {
        return $user->can('academics.manage') && ! $programme->isArchived();
    }

    /**
     * An ACTIVE teaching assignment for this exact roster and subject. A closed
     * assignment authorises nothing -- the successor plans, the predecessor
     * reads.
     */
    private function teaches(User $user, ?int $classId, ?int $teachingGroupId, int $subjectId): bool
    {
        $staffId = $user->staff?->id;

        if (! $staffId) {
            return false;
        }

        return ClassSubject::where('staff_id', $staffId)
            ->where('subject_id', $subjectId)
            ->when($classId !== null,
                fn ($q) => $q->where('class_id', $classId),
                fn ($q) => $q->where('teaching_group_id', $teachingGroupId))
            ->active()
            ->exists();
    }
}
