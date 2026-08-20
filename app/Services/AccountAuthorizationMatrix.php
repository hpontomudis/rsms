<?php

namespace App\Services;

use App\Models\User;

/**
 * The one source of truth for "who may provision or reset whose account"
 * (P2.1). Before this class existed, `StaffImportValidator`'s role
 * allowlist and `UserProvisioningService` considered only whether the
 * ACTOR held a coarse permission (`staff.import`, `users.reset-password`)
 * -- never the TARGET role -- so an `admin_staff` user (who legitimately
 * holds both permissions for onboarding purposes) could provision or
 * reset a `management`/`finance_staff`/another `admin_staff` account,
 * a real privilege-escalation path out of what was meant to be a narrow
 * onboarding permission.
 *
 * Used consistently by `StaffImportValidator` (provisioning, at
 * validation time so a forbidden row is rejected before import) and by
 * `UserProvisioningService` itself (both provisioning and reset, checked
 * there UNCONDITIONALLY). The service-level check is not optional
 * belt-and-suspenders: `AppServiceProvider`'s `Gate::before` returns
 * `true` for every ability check when the actor holds `super_admin`,
 * which bypasses `StaffPolicy` entirely for that role -- the service is
 * the only place that can actually stop a `super_admin` actor from
 * resetting another `super_admin`'s password through the ordinary Staff
 * UI, since it is a plain method call, never a `Gate`/`$this->authorize()`
 * call.
 */
class AccountAuthorizationMatrix
{
    /** @var array<string, string[]> actor role => provisionable target roles */
    private const PROVISION = [
        // 'principal' added here deliberately, super_admin-only: a school
        // has exactly one principal, and provisioning that login was
        // previously unreachable through any path at all. super_admin is
        // still deliberately excluded from its own target list -- the P1
        // bootstrap command remains the one sanctioned way to (re-)establish
        // a super_admin login.
        'super_admin' => ['teacher', 'admin_staff', 'finance_staff', 'management', 'principal'],
        'admin_staff' => ['teacher'],
    ];

    /** @var array<string, string[]> actor role => resettable target roles */
    private const RESET = [
        // Deliberately excludes super_admin -> super_admin: a super_admin
        // resetting another super_admin's password through the ordinary
        // Staff UI is denied here even though Gate::before would otherwise
        // let the actor reach this method at all. The only sanctioned way
        // to (re-)establish a super_admin login remains the P1 bootstrap
        // command, unchanged by this class. 'principal' IS included here
        // (unlike super_admin) -- there is no separate bootstrap path for a
        // principal login, so super_admin resetting one is the only way a
        // locked-out principal recovers access at all.
        'super_admin' => ['teacher', 'admin_staff', 'finance_staff', 'management', 'principal'],
        'admin_staff' => ['teacher'],
    ];

    public static function canProvision(User $actor, string $targetRole): bool
    {
        return self::actorMayActOn($actor, $targetRole, self::PROVISION);
    }

    public static function canResetPasswordFor(User $actor, User $target): bool
    {
        $targetRole = self::resolveSingleRole($target);

        if ($targetRole === null) {
            // Zero or more than one role on the target -- never guess which
            // one governs this decision. Fail closed, matching this
            // project's existing ResolvesUnambiguousUser discipline.
            return false;
        }

        return self::actorMayActOn($actor, $targetRole, self::RESET);
    }

    private static function actorMayActOn(User $actor, string $targetRole, array $matrix): bool
    {
        foreach ($matrix as $actorRole => $allowedTargets) {
            if ($actor->hasRole($actorRole) && in_array($targetRole, $allowedTargets, true)) {
                return true;
            }
        }

        return false;
    }

    private static function resolveSingleRole(User $user): ?string
    {
        $roles = $user->getRoleNames();

        return $roles->count() === 1 ? $roles->first() : null;
    }
}
