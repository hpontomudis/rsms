<?php

namespace App\Models\Concerns;

use App\Models\Staff;

/**
 * The exact-match rule both audit-derived evidence AND self-view
 * authorization depend on.
 *
 * `staff.user_id` carries no unique index, so more than one Staff row can
 * share one login. Given the Staff in question, their OWN user_id is not
 * ambiguous to read -- but if that same user_id is ALSO used by a different
 * Staff row, it is unclear which of them a shared login actually identifies
 * (an audit_logs entry could have been made by either; a logged-in User could
 * be viewing on behalf of either). That is the ambiguity this resolves: not
 * "which Staff does this User belong to", but "is this login exclusively this
 * Staff member's".
 *
 * Returns null whenever attribution would require a guess: no login at all,
 * or a login shared with another Staff row. Never `first()`.
 */
trait ResolvesUnambiguousUser
{
    private function unambiguousUserId(Staff $staff): ?int
    {
        if ($staff->user_id === null) {
            return null;
        }

        $sharing = Staff::where('user_id', $staff->user_id)->count();

        return $sharing === 1 ? $staff->user_id : null;
    }
}
