<?php

namespace App\ManagementInsights;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The one entry point callers use. Enforces the `management-insights.view`
 * permission once, then delegates to the registry -- individual providers
 * do NOT re-check authorization, matching the discipline this project uses
 * everywhere else (auth belongs at the boundary, not sprinkled through
 * each read).
 */
class ManagementInsightService
{
    public function __construct(private readonly ManagementInsightRegistry $registry) {}

    /**
     * @return list<ManagementInsight>
     */
    public function all(ManagementInsightScope $scope): array
    {
        $this->authorize($scope->actor);

        return $this->registry->all($scope);
    }

    private function authorize(User $actor): void
    {
        if (! $actor->can('management-insights.view')) {
            throw new AuthorizationException('This account is not permitted to view Management Insights.');
        }
    }
}
