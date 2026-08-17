<?php

namespace App\Policies;

use App\Models\Staff;
use App\Models\User;

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
     */
    public function resetPassword(User $user, Staff $staff): bool
    {
        return $user->can('users.reset-password');
    }

    public function import(User $user): bool
    {
        return $user->can('staff.import');
    }
}
