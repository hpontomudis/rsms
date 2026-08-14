<?php

namespace App\Policies;

use App\Models\PerformanceFramework;
use App\Models\User;

/**
 * Framework/Section/Indicator/RatingOption structure all shares this one
 * gate -- editing any of them IS editing the framework's structure, and that
 * structure is only ever mutable while the framework is draft. Livewire
 * components for sections/indicators/rating options authorize against the
 * parent framework via `update`, not a policy of their own.
 */
class PerformanceFrameworkPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('performance.view') || $user->can('performance.manage');
    }

    public function view(User $user, PerformanceFramework $framework): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('performance.manage');
    }

    /** Governs section/indicator/rating-option authoring too -- see class docblock. */
    public function update(User $user, PerformanceFramework $framework): bool
    {
        return $user->can('performance.manage') && $framework->isStructureEditable();
    }

    public function activate(User $user, PerformanceFramework $framework): bool
    {
        return $user->can('performance.manage') && $framework->isDraft();
    }

    public function archive(User $user, PerformanceFramework $framework): bool
    {
        return $user->can('performance.manage') && $framework->isActive();
    }

    public function delete(User $user, PerformanceFramework $framework): bool
    {
        return $user->can('performance.manage') && $framework->isDraft();
    }
}
