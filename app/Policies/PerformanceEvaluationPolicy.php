<?php

namespace App\Policies;

use App\Models\Concerns\ResolvesUnambiguousUser;
use App\Models\PerformanceEvaluation;
use App\Models\User;

/**
 * Reading, authoring and finalizing an evaluation is management's business
 * (performance.view / performance.manage), with exactly one carve-out: a
 * staff member may read their own FINALIZED evaluation, and only when their
 * login is exclusively theirs -- the same exact-match rule
 * (ResolvesUnambiguousUser) that decides whether audit-derived evidence can
 * be attributed to them at all. A shared login is unattributable evidence AND
 * an unsafe self-view target for the same reason: there is no reliable way to
 * tell which of two staff members is actually looking.
 *
 * Drafts are never shown to the staff member being evaluated -- an
 * evaluator's in-progress notes are not the finished, accountable record.
 * That mirrors the "draft is internal, published is external" split
 * AcademicRecordPolicy already draws for report cards.
 *
 * teacher and admin_staff hold neither performance permission by deliberate,
 * conservative grant (see RolesAndPermissionsSeeder) -- self-view works for
 * them anyway, because it is this carve-out, not a permission.
 */
class PerformanceEvaluationPolicy
{
    use ResolvesUnambiguousUser;

    public function viewAny(User $user): bool
    {
        return $user->can('performance.view') || $user->can('performance.manage');
    }

    public function view(User $user, PerformanceEvaluation $evaluation): bool
    {
        if ($user->can('performance.view') || $user->can('performance.manage')) {
            return true;
        }

        return $evaluation->isFinalized()
            && $evaluation->staff
            && $this->unambiguousUserId($evaluation->staff) === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('performance.manage');
    }

    /** Development-plan fields, responses, manual evidence, overall rating -- all draft-only. */
    public function update(User $user, PerformanceEvaluation $evaluation): bool
    {
        return $user->can('performance.manage') && $evaluation->isDraft();
    }

    public function finalize(User $user, PerformanceEvaluation $evaluation): bool
    {
        return $user->can('performance.manage') && $evaluation->isDraft();
    }

    public function delete(User $user, PerformanceEvaluation $evaluation): bool
    {
        return $user->can('performance.manage') && $evaluation->isDraft();
    }
}
