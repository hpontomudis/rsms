<?php

namespace App\ManagementInsights\Providers;

use App\ManagementInsights\ManagementInsight;
use App\ManagementInsights\ManagementInsightProvider;
use App\ManagementInsights\ManagementInsightScope;
use App\Models\Communication;
use Illuminate\Support\Facades\DB;

/**
 * (E) Published Communications with zero recipients reachable in RSMS.
 *
 * "Zero reachable" = the Communication has one or more materialized
 * `communication_recipients` rows (i.e. Publish actually resolved an
 * audience), but every one of those rows has a NULL `resolved_user_id`
 * (no login exists yet for any of them, per V8A's "recipient identity is
 * separate from resolved login" design).
 *
 * V8A has NO external delivery of any kind. This provider MUST NOT be
 * described as "failed delivery" or "parents were not notified" -- V9A-5
 * architecture review §14/§59 wording safety.
 *
 * Scoped by academic year via published_at's calendar year comparison
 * against the scope's `AcademicYear.start_date`/`end_date` -- Communications
 * carry no academic_year_id of their own.
 *
 * Reliability = reliable (V8A materialization is exhaustively tested).
 * Severity = attention when count > 0 -- a Guardian-audience Communication
 * with zero reachable recipients is a real operational fact worth
 * surfacing.
 */
class ZeroReachableCommunicationsInsightProvider implements ManagementInsightProvider
{
    public function key(): string
    {
        return 'zero_reachable_communications';
    }

    public function build(ManagementInsightScope $scope): ManagementInsight
    {
        $zeroReachable = Communication::query()
            ->where('status', 'published')
            ->whereBetween('published_at', [
                $scope->academicYear->start_date->startOfDay(),
                $scope->academicYear->end_date->endOfDay(),
            ])
            ->whereHas('recipients')
            ->whereDoesntHave('recipients', fn ($q) => $q->whereNotNull('resolved_user_id'))
            ->get(['id']);

        $count = $zeroReachable->count();

        return new ManagementInsight(
            key: $this->key(),
            category: 'communication',
            severity: $count > 0 ? ManagementInsight::SEVERITY_ATTENTION : ManagementInsight::SEVERITY_INFO,
            title: 'Published Communications with no RSMS-reachable recipients',
            description: "{$count} published Communications this academic year have recipients recorded, but none are currently reachable in RSMS.",
            count: $count,
            reliability: ManagementInsight::RELIABILITY_RELIABLE,
            sourceType: 'communication',
            sourceIds: $zeroReachable->pluck('id')->all(),
            actionRouteName: 'communications.index',
        );
    }
}
