<?php

namespace App\ManagementInsights\Providers;

use App\ManagementInsights\ManagementInsight;
use App\ManagementInsights\ManagementInsightProvider;
use App\ManagementInsights\ManagementInsightScope;
use App\Models\AcademicRecord;
use App\Models\Student;

/**
 * (G) Active Students without a Published Academic Record for the
 * explicitly selected COMPLETED Academic Period.
 *
 * The student population is derived from `students.status = 'active'`
 * directly -- NOT via the `class_student` pivot -- deliberately sidestepping
 * the known Foundation debt that `class_student` has no effective dating
 * (per PROJECT_STATUS.md Technical Debt and V9A-5 architecture review §4).
 *
 * A period is considered "completed" iff `end_date < today`. RSMS has no
 * existing "completed period" concept anywhere in the codebase, so V9A-5
 * introduces this specific, narrow comparison rather than inventing a
 * broader lifecycle status. If the scope's period is still current or
 * future, the fact is `unavailable` (not zero) -- publishing an Academic
 * Record for a period not yet finished is a legitimately different thing
 * from missing it.
 *
 * Reliability = reliable (both `students.status` and `AcademicRecord`'s
 * `draft`/`published`/`superseded` lifecycle are DB-enforced and
 * unambiguous). Severity = attention when count > 0 -- an unpublished
 * record for a completed period is a real operational task.
 */
class AcademicRecordPublicationInsightProvider implements ManagementInsightProvider
{
    public function key(): string
    {
        return 'students_missing_academic_record';
    }

    public function build(ManagementInsightScope $scope): ManagementInsight
    {
        $title = 'Active Students without a Published Academic Record';

        if ($scope->academicPeriod === null) {
            return new ManagementInsight(
                key: $this->key(),
                category: 'academics',
                severity: ManagementInsight::SEVERITY_INFO,
                title: $title,
                description: 'This fact requires an Academic Period to compute -- pick a completed one to see it.',
                count: null,
                reliability: ManagementInsight::RELIABILITY_UNAVAILABLE,
                reliabilityNote: 'Select an Academic Period.',
            );
        }

        if (! $scope->academicPeriod->end_date->isPast()) {
            return new ManagementInsight(
                key: $this->key(),
                category: 'academics',
                severity: ManagementInsight::SEVERITY_INFO,
                title: $title,
                description: 'This fact is only meaningful for a period that has ended.',
                count: null,
                reliability: ManagementInsight::RELIABILITY_UNAVAILABLE,
                reliabilityNote: 'Selected period has not ended yet.',
            );
        }

        $studentsWithPublished = AcademicRecord::query()
            ->published()
            ->where('academic_period_id', $scope->academicPeriod->id)
            ->pluck('student_id');

        $missing = Student::query()
            ->where('status', 'active')
            ->whereNotIn('id', $studentsWithPublished)
            ->get(['id']);

        $count = $missing->count();

        return new ManagementInsight(
            key: $this->key(),
            category: 'academics',
            severity: $count > 0 ? ManagementInsight::SEVERITY_ATTENTION : ManagementInsight::SEVERITY_INFO,
            title: $title,
            description: "{$count} active Students have no Published Academic Record for the selected completed period.",
            count: $count,
            reliability: ManagementInsight::RELIABILITY_RELIABLE,
            sourceType: 'student',
            sourceIds: $missing->pluck('id')->all(),
        );
    }
}
