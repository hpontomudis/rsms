<?php

namespace App\Models\Concerns;

use App\Models\Guardian;
use App\Models\Staff;
use App\Models\Student;

/**
 * The exact-match rule audit-derived evidence, self-view authorization, AND
 * Communication recipient resolution all depend on.
 *
 * `user_id` carries no unique index on staff, guardians or students, so more
 * than one row of the same type can share one login. Given the entity in
 * question, its OWN user_id is not ambiguous to read -- but if that same
 * user_id is ALSO used by another row of the same type, it is unclear which
 * one a shared login actually identifies. That is the ambiguity this
 * resolves: not "which entity does this User belong to", but "is this login
 * exclusively this entity's".
 *
 * Returns null whenever attribution would require a guess: no login at all,
 * or a login shared with another row of the same type. Never `first()`, and
 * never Eloquent's `HasOne` (which would silently pick one of several matches
 * rather than surface the ambiguity).
 */
trait ResolvesUnambiguousUser
{
    private function unambiguousUserId(Staff|Guardian|Student $entity): ?int
    {
        if ($entity->user_id === null) {
            return null;
        }

        $sharing = $entity::where('user_id', $entity->user_id)->count();

        return $sharing === 1 ? $entity->user_id : null;
    }
}
