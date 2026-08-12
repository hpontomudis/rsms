<?php

namespace App\Policies;

use App\Models\AcademicRecord;
use App\Models\Student;
use App\Models\User;

/**
 * Reading an issued report card is exactly as private as reading the data in
 * it, so the read gate is the SAME one the live preview already uses:
 * academics.view plus StudentPolicy::view. StudentPolicy is not widened, so a
 * teacher still reaches only students they teach.
 *
 * Issuing one is a management act. academics.manage is the permission that
 * already separates "puts things into force" from "does the teaching"
 * throughout Phase 5, and it needs no new sibling here.
 */
class AcademicRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('academics.view');
    }

    public function view(User $user, AcademicRecord $record): bool
    {
        return $this->canReadFor($user, $record->student);
    }

    /** Same gate as viewing the live report card for that student. */
    public function canReadFor(User $user, ?Student $student): bool
    {
        if (! $user->can('academics.view') || ! $student) {
            return false;
        }

        return $user->can('view', $student);
    }

    public function create(User $user): bool
    {
        return $user->can('academics.manage');
    }

    /** Only a draft's authored content is editable, and only by management. */
    public function update(User $user, AcademicRecord $record): bool
    {
        return $record->isDraft() && $user->can('academics.manage');
    }

    public function publish(User $user, AcademicRecord $record): bool
    {
        return $record->isDraft() && $user->can('academics.manage');
    }

    public function delete(User $user, AcademicRecord $record): bool
    {
        return $record->isDraft() && $user->can('academics.manage');
    }
}
