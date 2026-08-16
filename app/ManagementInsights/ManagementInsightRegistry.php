<?php

namespace App\ManagementInsights;

use App\ManagementInsights\Providers\AcademicRecordPublicationInsightProvider;
use App\ManagementInsights\Providers\DraftDailyJournalsInsightProvider;
use App\ManagementInsights\Providers\DraftPerformanceEvaluationsInsightProvider;
use App\ManagementInsights\Providers\DraftTeachingModulesInsightProvider;
use App\ManagementInsights\Providers\MissingSemesterProgrammeInsightProvider;
use App\ManagementInsights\Providers\StaffWithoutCategoryInsightProvider;
use App\ManagementInsights\Providers\ZeroReachableCommunicationsInsightProvider;
use Illuminate\Contracts\Container\Container;

/**
 * A closed, hardcoded registry -- same discipline as EvidenceRegistry.
 * An eighth insight is a code change that adds a provider class and lists
 * it here, forcing review; a data-driven registry would let a query
 * nobody vetted quietly show up on a management dashboard.
 *
 * Explicitly excluded (see V9A-5 architecture review §2 and PROJECT_STATUS.md
 * Foundation Technical Debt):
 *
 *   - No `class_teacher`-based provider. `class_teacher` has no effective
 *     dating and no protection against an incomplete handover, so a stale
 *     row may validly read as "current." Absence here is structural.
 *
 *   - No standalone `class_student`-chronology provider. The pivot has no
 *     effective dating and no partial-unique guard against a student
 *     holding two 'active' rows.
 *
 *   - No Assessment-missing-results provider. Its `scoreSheetStudents()`
 *     roster for class-backed assessments transitively depends on
 *     `class_student`. Deferred until Foundation remediation.
 *
 *   - No Finance provider. Deferred by V9A-5 policy, not by data
 *     reliability -- financial amounts are sensitive and outside the
 *     first management-narrative scope.
 */
class ManagementInsightRegistry
{
    /**
     * @var array<string, class-string<ManagementInsightProvider>>
     */
    public const PROVIDERS = [
        'missing_semester_programmes' => MissingSemesterProgrammeInsightProvider::class,
        'draft_teaching_modules' => DraftTeachingModulesInsightProvider::class,
        'draft_daily_journals' => DraftDailyJournalsInsightProvider::class,
        'draft_performance_evaluations' => DraftPerformanceEvaluationsInsightProvider::class,
        'zero_reachable_communications' => ZeroReachableCommunicationsInsightProvider::class,
        'staff_without_category' => StaffWithoutCategoryInsightProvider::class,
        'students_missing_academic_record' => AcademicRecordPublicationInsightProvider::class,
    ];

    public function __construct(private readonly Container $container) {}

    /**
     * @return list<ManagementInsight>
     */
    public function all(ManagementInsightScope $scope): array
    {
        $insights = [];

        foreach (self::PROVIDERS as $key => $providerClass) {
            /** @var ManagementInsightProvider $provider */
            $provider = $this->container->make($providerClass);
            $insights[] = $provider->build($scope);
        }

        return $insights;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys(self::PROVIDERS);
    }
}
