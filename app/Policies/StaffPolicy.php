<?php

namespace App\Policies;

use App\Models\Staff;
use App\Models\User;
use App\Services\AccountAuthorizationMatrix;

class StaffPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('staff.view');
    }

    public function view(User $user, Staff $staff): bool
    {
        return $user->can('staff.view');
    }

    public function create(User $user): bool
    {
        return $user->can('staff.create');
    }

    public function update(User $user, Staff $staff): bool
    {
        return $user->can('staff.update');
    }

    public function delete(User $user, Staff $staff): bool
    {
        return $user->can('staff.delete');
    }

    /**
     * Deliberately NOT tied to staff.update (P2B) -- being able to edit a
     * Staff profile must not, by itself, grant the power to reset someone's
     * login credential. A dedicated permission, checked here rather than
     * inline in the Livewire component, so denial goes through Laravel's
     * normal AuthorizationException path (Livewire serializes that into a
     * proper 403 response; a raw abort_unless() inside a component action
     * does not survive the Livewire test client's snapshot round-trip).
     *
     * Also checks the TARGET's role (P2.1) via AccountAuthorizationMatrix
     * -- `users.reset-password` alone previously let any holder (i.e.
     * admin_staff) reset ANY account regardless of how privileged it was.
     * Note this check is a no-op for a super_admin actor specifically,
     * since AppServiceProvider's Gate::before short-circuits every
     * ability check to `true` before this method ever runs for that role
     * -- the real enforcement for "no super_admin -> super_admin reset"
     * lives in UserProvisioningService itself, which Gate::before cannot
     * bypass. See that class's docblock.
     */
    public function resetPassword(User $user, Staff $staff): bool
    {
        if (! $user->can('users.reset-password')) {
            return false;
        }

        if (! $staff->user) {
            return false;
        }

        return AccountAuthorizationMatrix::canResetPasswordFor($user, $staff->user);
    }

    public function import(User $user): bool
    {
        return $user->can('staff.import');
    }
}
