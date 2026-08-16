<?php

namespace App\ManagementInsights\Providers;

use App\ManagementInsights\ManagementInsight;
use App\ManagementInsights\ManagementInsightProvider;
use App\ManagementInsights\ManagementInsightScope;
use App\Models\DailyJournal;

/**
 * (C) Draft Daily Journals within the scope's academic year (and academic
 * period, if one is selected).
 *
 * Reliability = reliable. Severity = info: like Draft Modules, a Draft
 * Journal is a workflow state, not an incomplete-teaching claim. A Journal
 * records what happened; absence of a Journal is NOT absence of teaching.
 * Wording deliberately avoids "missing" or "incomplete teaching," per
 * V9A-5 architecture review §12/§59 wording safety.
 */
class DraftDailyJournalsInsightProvider implements ManagementInsightProvider
{
    public function key(): string
    {
        return 'draft_daily_journals';
    }

    public function build(ManagementInsightScope $scope): ManagementInsight
    {
        $query = DailyJournal::query()
            ->where('status', 'draft')
            ->where('academic_year_id', $scope->academicYear->id);

        if ($scope->academicPeriod !== null) {
            $query->where('academic_period_id', $scope->academicPeriod->id);
        }

        $drafts = $query->get(['id']);
        $count = $drafts->count();

        return new ManagementInsight(
            key: $this->key(),
            category: 'teaching_planning',
            severity: ManagementInsight::SEVERITY_INFO,
            title: 'Draft Daily Journals',
            description: "{$count} Daily Journal entries are still in Draft.",
            count: $count,
            reliability: ManagementInsight::RELIABILITY_RELIABLE,
            sourceType: 'daily_journal',
            sourceIds: $drafts->pluck('id')->all(),
        );
    }
}
