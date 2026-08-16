<?php

namespace App\ManagementInsights\Providers;

use App\ManagementInsights\ManagementInsight;
use App\ManagementInsights\ManagementInsightProvider;
use App\ManagementInsights\ManagementInsightScope;
use App\Models\AnnualProgramme;
use App\Models\ClassSubject;
use App\Models\SemesterProgramme;

/**
 * (A) Active ClassSubject assignments missing a Semester Programme for the
 * explicitly selected AcademicPeriod.
 *
 * "Missing" = for an active assignment (`class_subject.ended_on IS NULL`)
 * whose academic year matches the scope, either (i) no AnnualProgramme
 * exists for its roster+subject at all, or (ii) an AnnualProgramme exists
 * but has no SemesterProgramme for the scope's academic_period_id.
 *
 * Reliability = reliable. Both class_subject (effective-dated) and the
 * annual/semester programme chain (roster-anchored, no class_teacher
 * dependence) are among the strongest sources this codebase has.
 *
 * Severity = attention when count > 0 -- planning completeness is
 * genuinely actionable, unlike a Draft-Journal count which is normal
 * workflow state.
 *
 * If the scope has no academicPeriod, the fact is `unavailable` (a period
 * is required to say "no Semester Programme for THIS period"), not zero.
 */
class MissingSemesterProgrammeInsightProvider implements ManagementInsightProvider
{
    public function key(): string
    {
        return 'missing_semester_programmes';
    }

    public function build(ManagementInsightScope $scope): ManagementInsight
    {
        $title = 'Active teaching assignments missing a Semester Programme';

        if ($scope->academicPeriod === null) {
            return new ManagementInsight(
                key: $this->key(),
                category: 'teaching_planning',
                severity: ManagementInsight::SEVERITY_INFO,
                title: $title,
                description: 'This fact requires an Academic Period to compute -- pick one to see it.',
                count: null,
                reliability: ManagementInsight::RELIABILITY_UNAVAILABLE,
                reliabilityNote: 'Select an Academic Period.',
            );
        }

        $activeAssignments = ClassSubject::query()
            ->active()
            ->whereHas('subject')
            ->get()
            ->filter(fn (ClassSubject $cs) => $cs->academicYear()?->id === $scope->academicYear->id);

        $missing = $activeAssignments->filter(function (ClassSubject $cs) use ($scope): bool {
            $annual = AnnualProgramme::query()
                ->where('subject_id', $cs->subject_id)
                ->where('academic_year_id', $scope->academicYear->id)
                ->when($cs->isClassBacked(),
                    fn ($q) => $q->where('class_id', $cs->class_id),
                    fn ($q) => $q->where('teaching_group_id', $cs->teaching_group_id))
                ->first();

            if ($annual === null) {
                return true;
            }

            return ! SemesterProgramme::query()
                ->where('annual_programme_id', $annual->id)
                ->where('academic_period_id', $scope->academicPeriod->id)
                ->exists();
        })->values();

        $count = $missing->count();

        return new ManagementInsight(
            key: $this->key(),
            category: 'teaching_planning',
            severity: $count > 0 ? ManagementInsight::SEVERITY_ATTENTION : ManagementInsight::SEVERITY_INFO,
            title: $title,
            description: "{$count} active teaching assignments have no Semester Programme for the selected period.",
            count: $count,
            reliability: ManagementInsight::RELIABILITY_RELIABLE,
            sourceType: 'class_subject',
            sourceIds: $missing->pluck('id')->all(),
        );
    }
}
