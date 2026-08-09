<?php

namespace App\Policies;

use App\Models\FeeStructure;
use App\Models\User;

class FeeStructurePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('finance.view');
    }

    public function view(User $user, FeeStructure $feeStructure): bool
    {
        return $user->can('finance.view');
    }

    public function create(User $user): bool
    {
        return $user->can('finance.manage');
    }

    public function update(User $user, FeeStructure $feeStructure): bool
    {
        return $user->can('finance.manage');
    }

    public function delete(User $user, FeeStructure $feeStructure): bool
    {
        return $user->can('finance.manage');
    }
}
