<?php

namespace App\Policies;

use App\Models\ClassSubject;
use App\Models\DailyJournal;
use App\Models\User;

/**
 * A journal is a record of what happened, so the rules tighten as it becomes
 * history rather than loosening.
 *
 * Draft: the assigned teacher writes and edits it.
 * Finalized: the teacher is out; a manager may correct it, and every correction
 * is audited. There is no separate 'corrected' state because the audit log
 * already is one.
 *
 * Backfill onto a CLOSED assignment is a manager-only correction privilege --
 * unlike a module, which may never be written against closed history at all,
 * because a plan written after the teaching would be a fiction while a record
 * of teaching that genuinely happened is not.
 */
class DailyJournalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('academics.view');
    }

    public function view(User $user, DailyJournal $journal): bool
    {
        return $user->can('academics.view');
    }

    public function createFor(User $user, ClassSubject $assignment): bool
    {
        if (! $assignment->isActive()) {
            return $this->canBackfill($user, $assignment);
        }

        if (! $user->can('academics.record')) {
            return false;
        }

        return $this->owns($user, $assignment);
    }

    /** Only a manager may open a journal on an assignment that has ended. */
    public function backfillFor(User $user, ClassSubject $assignment): bool
    {
        return ! $assignment->isActive() && $this->canBackfill($user, $assignment);
    }

    public function update(User $user, DailyJournal $journal): bool
    {
        // Correcting history is a management act, not a recording one, so it is
        // gated on academics.manage alone. (This once also relied on a principal
        // NOT holding academics.record; the principal role now does hold it, so
        // the distinction here is purely that finalized journals need `manage`,
        // which teachers never have.)
        if ($journal->isFinalized()) {
            return $user->can('academics.manage');
        }

        if (! $user->can('academics.record')) {
            return false;
        }

        $assignment = $journal->classSubject;

        if (! $assignment) {
            return false;
        }

        if (! $assignment->isActive()) {
            return $this->canBackfill($user, $assignment);
        }

        return $this->owns($user, $assignment);
    }

    public function finalize(User $user, DailyJournal $journal): bool
    {
        return $journal->isDraft() && $this->update($user, $journal);
    }

    public function delete(User $user, DailyJournal $journal): bool
    {
        return $journal->isDraft() && $this->update($user, $journal);
    }

    private function canBackfill(User $user, ClassSubject $assignment): bool
    {
        return $user->can('academics.manage');
    }

    private function owns(User $user, ClassSubject $assignment): bool
    {
        if (! $user->hasRole('teacher')) {
            return true;
        }

        $staffId = $user->staff?->id;

        return $staffId !== null && $assignment->staff_id === $staffId;
    }
}
