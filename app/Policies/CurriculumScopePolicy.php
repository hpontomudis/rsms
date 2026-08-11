<?php

namespace App\Policies;

use App\Models\CurriculumScope;
use App\Models\User;

/**
 * Read for anyone with academics.view -- curriculum standards name no
 * students. Write for academic management, and only while the parent
 * curriculum is a draft: the model guard refuses regardless of role, because
 * activated standards are history rather than a permission question.
 */
class CurriculumScopePolicy
{
    public function view(User $user, CurriculumScope $scope): bool
    {
        return $user->can('academics.view');
    }

    public function create(User $user): bool
    {
        return $user->can('academics.manage');
    }

    public function update(User $user, CurriculumScope $scope): bool
    {
        return $user->can('academics.manage') && $scope->curriculum->isDraft();
    }

    public function delete(User $user, CurriculumScope $scope): bool
    {
        return $this->update($user, $scope);
    }
}
