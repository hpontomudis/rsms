<?php

namespace App\ManagementInsights\Providers;

use App\ManagementInsights\ManagementInsight;
use App\ManagementInsights\ManagementInsightProvider;
use App\ManagementInsights\ManagementInsightScope;
use App\Models\PerformanceEvaluation;

/**
 * (D) Draft Performance Evaluations.
 *
 * ADMINISTRATIVE LIFECYCLE FACT ONLY -- this provider queries `status`
 * alone. It never touches ratings, evidence text, strengths,
 * development_priorities, overall_rating_option_id, or any of the
 * PerformanceEvaluation fields that carry appraisal content, per V7A's
 * "system evidence never sets or suggests a rating" firewall extended to
 * the read side.
 *
 * The `review_date` column exists on PerformanceEvaluation but no code
 * anywhere in this codebase currently computes "overdue" from it -- V9A-5
 * deliberately does NOT invent that rule (see V9A-5 architecture review
 * §13-D and §15). Only a status count is emitted here.
 *
 * `academic_year_id` on PerformanceEvaluation is nullable, so this scope
 * filter includes evaluations whose year matches the current scope AND
 * evaluations with a null year (which the schema permits) -- for the
 * latter, filtering them out silently would misrepresent the count.
 *
 * Reliability = reliable. Severity = info: a Draft evaluation is normal
 * workflow.
 */
class DraftPerformanceEvaluationsInsightProvider implements ManagementInsightProvider
{
    public function key(): string
    {
        return 'draft_performance_evaluations';
    }

    public function build(ManagementInsightScope $scope): ManagementInsight
    {
        $drafts = PerformanceEvaluation::query()
            ->where('status', 'draft')
            ->where(fn ($q) => $q
                ->where('academic_year_id', $scope->academicYear->id)
                ->orWhereNull('academic_year_id'))
            ->get(['id']);

        $count = $drafts->count();

        return new ManagementInsight(
            key: $this->key(),
            category: 'staff',
            severity: ManagementInsight::SEVERITY_INFO,
            title: 'Draft Performance Evaluations',
            description: "{$count} Staff Performance Evaluations remain in Draft.",
            count: $count,
            reliability: ManagementInsight::RELIABILITY_RELIABLE,
            sourceType: 'performance_evaluation',
            sourceIds: $drafts->pluck('id')->all(),
            actionRouteName: 'performance.evaluations.index',
            actionRouteParams: ['status' => 'draft'],
        );
    }
}
