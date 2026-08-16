<?php

namespace App\ManagementInsights\Providers;

use App\ManagementInsights\ManagementInsight;
use App\ManagementInsights\ManagementInsightProvider;
use App\ManagementInsights\ManagementInsightScope;
use App\Models\Staff;

/**
 * (F) Active Staff records with no StaffCategory assigned.
 *
 * Data-quality/administrative-completeness fact -- NOT a performance
 * judgment. The migration that added `staff_category_id` deliberately made
 * it nullable and did NOT backfill existing rows: "existing staff remain
 * uncategorized until explicitly assigned, and a Performance Evaluation
 * refuses to start for an uncategorized staff member rather than guessing."
 * So "0" here is the target state, and any non-zero count means a real
 * pending assignment task.
 *
 * Reliability = reliable. Severity = info -- surfacing the fact is what
 * matters; the actionable follow-up is administrative, not urgent.
 *
 * Scope-independent: this is a school-wide staff-record fact, not tied to
 * an academic year or period. The scope DTO is accepted for interface
 * consistency but ignored.
 */
class StaffWithoutCategoryInsightProvider implements ManagementInsightProvider
{
    public function key(): string
    {
        return 'staff_without_category';
    }

    public function build(ManagementInsightScope $scope): ManagementInsight
    {
        $uncategorized = Staff::query()
            ->where('status', 'active')
            ->whereNull('staff_category_id')
            ->get(['id']);

        $count = $uncategorized->count();

        return new ManagementInsight(
            key: $this->key(),
            category: 'staff',
            severity: ManagementInsight::SEVERITY_INFO,
            title: 'Active Staff with no Staff Category',
            description: "{$count} active Staff records have no Staff Category assigned.",
            count: $count,
            reliability: ManagementInsight::RELIABILITY_RELIABLE,
            sourceType: 'staff',
            sourceIds: $uncategorized->pluck('id')->all(),
        );
    }
}
