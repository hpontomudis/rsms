<?php

namespace App\ManagementInsights\Providers;

use App\ManagementInsights\ManagementInsight;
use App\ManagementInsights\ManagementInsightProvider;
use App\ManagementInsights\ManagementInsightScope;
use App\Models\ClassSubject;
use App\Models\TeachingModule;

/**
 * (B) Draft Teaching Modules on active assignments within the scope's
 * academic year.
 *
 * Closed historical assignment Drafts are excluded -- they cannot be
 * marked ready (`TeachingModuleService::markReady()` requires an active
 * assignment) and are effectively abandoned; counting them here would
 * misrepresent current planning workload.
 *
 * Reliability = reliable. Severity = info: a Draft Module is normal
 * workflow state (teachers draft before marking ready), not itself
 * actionable, per V9A-5 architecture review §17.
 */
class DraftTeachingModulesInsightProvider implements ManagementInsightProvider
{
    public function key(): string
    {
        return 'draft_teaching_modules';
    }

    public function build(ManagementInsightScope $scope): ManagementInsight
    {
        $activeAssignmentIds = ClassSubject::query()
            ->active()
            ->get()
            ->filter(fn (ClassSubject $cs) => $cs->academicYear()?->id === $scope->academicYear->id)
            ->pluck('id');

        $drafts = TeachingModule::query()
            ->where('status', 'draft')
            ->whereIn('class_subject_id', $activeAssignmentIds)
            ->get(['id']);

        $count = $drafts->count();

        return new ManagementInsight(
            key: $this->key(),
            category: 'teaching_planning',
            severity: ManagementInsight::SEVERITY_INFO,
            title: 'Draft Teaching Modules',
            description: "{$count} Teaching Modules remain in Draft on currently active assignments.",
            count: $count,
            reliability: ManagementInsight::RELIABILITY_RELIABLE,
            sourceType: 'teaching_module',
            sourceIds: $drafts->pluck('id')->all(),
        );
    }
}
