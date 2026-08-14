<?php

namespace App\Policies;

use App\Models\Communication;
use App\Models\User;

/**
 * A dedicated policy, deliberately not folded into or widening StudentPolicy
 * or any other existing one -- Communication is an ACTION (something is sent
 * to someone), not just a read, and that is a materially different risk from
 * every read-scoping this codebase has done so far (V8A architecture review
 * item 29).
 *
 * `communications.manage` is broad at the permission level for BOTH principal
 * and teacher -- the same permission name -- but teacher's actual authority
 * is scoped down here to their own current assignments. The raw permission
 * never authorises a school-wide (or any unrelated) audience; see `owns()`
 * and TeacherAudienceScope, which CommunicationService also uses to validate
 * every audience rule a teacher adds, both at add-time and again at publish.
 * Neither layer trusts the other alone.
 */
class CommunicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('communications.view') || $user->can('communications.manage');
    }

    /**
     * A non-teacher holder of communications.view/manage (principal,
     * management, super_admin) sees everything -- that permission tier is not
     * scoped. A teacher's raw communications.manage does NOT extend to
     * browsing everyone else's communications -- see the class docblock's
     * "raw permission never authorises..." principle applied to reading, not
     * just targeting -- so a teacher sees only what they authored, plus
     * anything they personally are a materialized recipient of.
     *
     * Recipient access itself depends only on a materialized
     * CommunicationRecipient row resolving to this login -- current Class or
     * Teaching Group membership grants NOTHING here, exactly as the review
     * specified.
     */
    public function view(User $user, Communication $communication): bool
    {
        if ($user->can('communications.view') && ! $user->hasRole('teacher')) {
            return true;
        }

        if ($user->can('communications.manage') && ! $user->hasRole('teacher')) {
            return true;
        }

        if ($communication->created_by_user_id === $user->id) {
            return true;
        }

        if ($communication->isDraft()) {
            return false;
        }

        return $communication->recipients()->where('resolved_user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('communications.manage');
    }

    public function update(User $user, Communication $communication): bool
    {
        return $user->can('communications.manage') && $communication->isDraft() && $this->owns($user, $communication);
    }

    /** Same gate as update() -- audience rules are draft-only content, same as title/body. */
    public function manageAudience(User $user, Communication $communication): bool
    {
        return $this->update($user, $communication);
    }

    public function publish(User $user, Communication $communication): bool
    {
        return $user->can('communications.manage') && $communication->isDraft() && $this->owns($user, $communication);
    }

    public function delete(User $user, Communication $communication): bool
    {
        return $communication->isDraft() && $this->owns($user, $communication);
    }

    /**
     * Principal/management-tier archiving is unconditional within their
     * permission; a teacher may archive only a Communication they authored,
     * and only for as long as its audience rules stay within their current
     * scope -- re-checked live, not read off what was true at publish time.
     * CommunicationService::archive() re-validates the same thing; this is
     * the authorization gate, not the source of truth for the check itself.
     */
    public function archive(User $user, Communication $communication): bool
    {
        if (! $user->can('communications.manage') || $communication->isDraft()) {
            return false;
        }

        if (! $user->hasRole('teacher')) {
            return true;
        }

        return $communication->created_by_user_id === $user->id;
    }

    /**
     * Teachers are scoped to Communications they authored; anyone else
     * holding the permission (principal, super_admin) is not scoped at all.
     * Identical shape to TeachingModulePolicy::owns().
     */
    private function owns(User $user, Communication $communication): bool
    {
        if (! $user->hasRole('teacher')) {
            return true;
        }

        return $communication->created_by_user_id === $user->id;
    }
}
