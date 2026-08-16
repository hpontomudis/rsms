<?php

namespace Tests\Feature;

use App\ManagementInsights\ManagementInsight;
use App\ManagementInsights\ManagementInsightRegistry;
use App\ManagementInsights\ManagementInsightScope;
use App\ManagementInsights\ManagementInsightService;
use App\ManagementInsights\Providers\AcademicRecordPublicationInsightProvider;
use App\ManagementInsights\Providers\DraftDailyJournalsInsightProvider;
use App\ManagementInsights\Providers\DraftPerformanceEvaluationsInsightProvider;
use App\ManagementInsights\Providers\DraftTeachingModulesInsightProvider;
use App\ManagementInsights\Providers\MissingSemesterProgrammeInsightProvider;
use App\ManagementInsights\Providers\StaffWithoutCategoryInsightProvider;
use App\ManagementInsights\Providers\ZeroReachableCommunicationsInsightProvider;
use App\Models\AcademicPeriod;
use App\Models\AcademicRecord;
use App\Models\Communication;
use App\Models\CommunicationRecipient;
use App\Models\DailyJournal;
use App\Models\PerformanceEvaluation;
use App\Models\PerformanceFramework;
use App\Models\Staff;
use App\Models\StaffCategory;
use App\Models\Student;
use App\Models\User;
use App\Services\AnnualProgrammeService;
use App\Services\CommunicationService;
use App\Services\DailyJournalService;
use App\Services\SemesterProgrammeService;
use App\Services\TeachingModuleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Feature\Concerns\BuildsCommunicationFixtures;
use Tests\TestCase;

/**
 * Deterministic provider tests -- exact counts, exact source IDs, exact
 * scope handling, exact unavailable-vs-zero behaviour. No AI required for
 * any of these; every fact must be reproducible without an assistant.
 *
 * Registry-level firewall tests are also here: the absence of a
 * `class_teacher`-based provider, a standalone `class_student`-chronology
 * provider, and an Assessment-missing-results provider is asserted
 * STRUCTURALLY (no key with those substrings can appear in the closed
 * registry), so a well-meaning future addition triggers a test failure.
 */
class ManagementInsightProvidersTest extends TestCase
{
    use BuildsCommunicationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCommunicationReferenceData();
    }

    private function scope(?AcademicPeriod $period = null, ?User $actor = null): ManagementInsightScope
    {
        return new ManagementInsightScope(
            academicYear: $this->year,
            academicPeriod: $period,
            actor: $actor ?? $this->principalUser(),
        );
    }

    // ============================================================ registry

    public function test_the_registry_exposes_exactly_the_seven_approved_v1_keys(): void
    {
        $registry = app(ManagementInsightRegistry::class);
        $this->assertSame([
            'missing_semester_programmes',
            'draft_teaching_modules',
            'draft_daily_journals',
            'draft_performance_evaluations',
            'zero_reachable_communications',
            'staff_without_category',
            'students_missing_academic_record',
        ], $registry->keys());
    }

    public function test_no_class_teacher_or_class_student_or_assessment_missing_results_provider_exists(): void
    {
        // Structural firewall: the absence of these keys is load-bearing --
        // class_teacher's stale-handover defect, class_student's lack of
        // effective dating, and Assessment's roster-through-class_student
        // dependency all disqualify insights from V9A-5 v1. A well-meaning
        // future addition of any of these keys should break this test.
        $keys = app(ManagementInsightRegistry::class)->keys();
        foreach ($keys as $key) {
            $this->assertStringNotContainsStringIgnoringCase('class_teacher', $key);
            $this->assertStringNotContainsStringIgnoringCase('homeroom', $key);
            $this->assertStringNotContainsStringIgnoringCase('class_student', $key);
            $this->assertStringNotContainsStringIgnoringCase('assessment_missing', $key);
            $this->assertStringNotContainsStringIgnoringCase('missing_results', $key);
            $this->assertStringNotContainsStringIgnoringCase('finance', $key);
        }
    }

    // ==================================================== service authorization

    public function test_the_service_refuses_users_without_management_insights_view(): void
    {
        $service = app(ManagementInsightService::class);
        $teacher = $this->teacherUserFor('sarah');

        $this->assertFalse(Gate::forUser($teacher)->allows('management-insights.view'));

        $this->expectException(AuthorizationException::class);
        $service->all($this->scope(actor: $teacher));
    }

    public function test_the_service_allows_principal_and_management(): void
    {
        $service = app(ManagementInsightService::class);
        $this->assertIsArray($service->all($this->scope(actor: $this->principalUser())));
        $this->assertIsArray($service->all($this->scope(actor: $this->managementUser())));
    }

    public function test_the_service_refuses_admin_staff(): void
    {
        $service = app(ManagementInsightService::class);
        $adminStaff = $this->adminStaffUser();

        $this->assertFalse(Gate::forUser($adminStaff)->allows('management-insights.view'));

        $this->expectException(AuthorizationException::class);
        $service->all($this->scope(actor: $adminStaff));
    }

    // ============================================================ Provider A

    public function test_missing_semester_programme_needs_a_period_or_reports_unavailable(): void
    {
        $insight = app(MissingSemesterProgrammeInsightProvider::class)->build($this->scope(period: null));

        $this->assertSame(ManagementInsight::RELIABILITY_UNAVAILABLE, $insight->reliability);
        $this->assertNull($insight->count);
    }

    public function test_missing_semester_programme_counts_active_assignments_without_a_matching_prosem(): void
    {
        $period = $this->period('Semester 1');

        // Sarah: has both Annual and Semester programme => excluded
        $sarahAssignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $sarahAnnual = app(AnnualProgrammeService::class)->createForClass(
            $sarahAssignment->schoolClass, $this->subject('Maths'), $this->pathway(),
        );
        app(AnnualProgrammeService::class)->addItem($sarahAnnual, $this->pathwayItem(1), $period, 8);
        app(SemesterProgrammeService::class)->create($sarahAnnual, $period);

        // Eka: assignment exists, no annual/semester programme => included
        $ekaAssignment = $this->assignmentFor('Year 6A', 'Maths', 'eka');

        // Budi: closed assignment, doesn't matter either way (excluded by active filter)
        $budiAssignment = $this->assignmentFor('Year 5A', 'Eng', 'budi');
        $budiAssignment->update(['ended_on' => '2026-08-01']);

        $insight = app(MissingSemesterProgrammeInsightProvider::class)->build($this->scope($period));

        $this->assertSame(ManagementInsight::RELIABILITY_RELIABLE, $insight->reliability);
        $this->assertSame(1, $insight->count);
        $this->assertSame([$ekaAssignment->id], $insight->sourceIds);
        $this->assertSame(ManagementInsight::SEVERITY_ATTENTION, $insight->severity);
    }

    // ============================================================ Provider B

    public function test_draft_teaching_modules_excludes_closed_and_non_draft(): void
    {
        $activeAssignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $modules = app(TeachingModuleService::class);
        $scope = $this->scopeFor('C');
        $this->restoreActive($this->curriculum());

        $draftOnActive = $modules->create($activeAssignment, $scope, [
            'title' => 'Draft on active', 'planned_activity' => 'x.',
        ]);

        $readyOnActive = $modules->create($activeAssignment, $scope, [
            'title' => 'Ready on active', 'planned_activity' => 'y.',
        ]);
        $modules->linkObjective($readyOnActive, $this->objectiveIn($scope, 'Maths', 'TP 1', 1));
        $modules->markReady($readyOnActive->fresh());

        // A separate assignment we then close AFTER creating a draft on it --
        // the draft must be excluded, since it's on a now-closed assignment.
        $draftAssignment = $this->assignmentFor('Year 6A', 'Maths', 'eka');
        $modules->create($draftAssignment, $scope, [
            'title' => 'Draft to be orphaned', 'planned_activity' => 'z.',
        ]);
        $draftAssignment->update(['ended_on' => '2026-08-01']);

        $insight = app(DraftTeachingModulesInsightProvider::class)->build($this->scope());

        $this->assertSame(1, $insight->count);
        $this->assertSame([$draftOnActive->id], $insight->sourceIds);
        $this->assertSame(ManagementInsight::SEVERITY_INFO, $insight->severity);
    }

    // ============================================================ Provider C

    public function test_draft_daily_journals_counts_only_drafts_in_scope(): void
    {
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $journals = app(DailyJournalService::class);
        $scope = $this->scopeFor('C');
        $this->restoreActive($this->curriculum());

        $draft = $journals->create($assignment, $scope, $this->period('Semester 1'), '2026-09-15',
            $this->staff('sarah'), ['topic' => 'x', 'actual_activity' => 'y']);

        $finalized = $journals->create($assignment, $scope, $this->period('Semester 1'), '2026-09-16',
            $this->staff('sarah'), ['topic' => 'a', 'actual_activity' => 'b']);
        $journals->linkObjective($finalized, $this->objectiveIn($scope, 'Maths', 'TP 1', 1));
        $journals->finalize($finalized->fresh());

        $insight = app(DraftDailyJournalsInsightProvider::class)->build($this->scope($this->period('Semester 1')));

        $this->assertSame(1, $insight->count);
        $this->assertSame([$draft->id], $insight->sourceIds);
    }

    // ============================================================ Provider D

    public function test_draft_performance_evaluations_counts_only_draft_status(): void
    {
        $framework = $this->activeFramework();
        $draft = PerformanceEvaluation::create($this->evaluationAttributes($framework, [
            'staff_id' => $this->teacherStaff('sarah')->id, 'status' => 'draft',
        ]));
        // A finalized one, which must be excluded.
        PerformanceEvaluation::create($this->evaluationAttributes($framework, [
            'staff_id' => $this->teacherStaff('eka')->id, 'status' => 'finalized',
            'finalized_at' => now(), 'finalized_by_user_id' => $this->principalUser()->id,
            'period_end' => '2026-12-01',
        ]));

        $insight = app(DraftPerformanceEvaluationsInsightProvider::class)->build($this->scope());

        $this->assertSame(1, $insight->count);
        $this->assertSame([$draft->id], $insight->sourceIds);
    }

    public function test_performance_provider_never_exposes_ratings_or_evidence(): void
    {
        // Structural firewall: the DTO has no field capable of representing
        // ratings/evidence, and the provider never loads them. Prove the
        // DTO shape by inspecting a real generated insight.
        $framework = $this->activeFramework();
        PerformanceEvaluation::create($this->evaluationAttributes($framework, [
            'staff_id' => $this->teacherStaff('sarah')->id, 'status' => 'draft',
            'summary' => 'CONFIDENTIAL evaluator narrative',
            'strengths' => 'CONFIDENTIAL strengths',
            'development_priorities' => 'CONFIDENTIAL priorities',
        ]));

        $insight = app(DraftPerformanceEvaluationsInsightProvider::class)->build($this->scope());

        $serialized = json_encode(get_object_vars($insight));
        $this->assertStringNotContainsString('CONFIDENTIAL', $serialized);
    }

    // ============================================================ Provider E

    public function test_zero_reachable_communications_finds_communications_with_recipients_but_no_reachable_ones(): void
    {
        $principal = $this->principalUser();
        $this->teacherStaff('sarah');

        // A published Communication whose recipients all have resolved_user_id = null.
        $unreachable = $this->communications()->createDraft($principal, [
            'display_sender' => 'Rahai School', 'title' => 'Unreachable audience', 'body' => 'x',
        ]);
        $guardian = $this->guardianNamed('Rudi', 'Wijaya', '0812-0001'); // no user_id
        $this->communications()->addAudienceRule($unreachable, $principal, [
            'rule_type' => 'selected_guardians', 'ids' => [$guardian->id],
        ]);
        $publishedUnreachable = $this->communications()->publish($unreachable->fresh(), $principal);

        // A published Communication with at least one reachable recipient (staff).
        $reachable = $this->communications()->createDraft($principal, [
            'display_sender' => 'Rahai School', 'title' => 'Reachable', 'body' => 'x',
        ]);
        $this->communications()->addAudienceRule($reachable, $principal, ['rule_type' => 'all_staff']);
        $this->communications()->publish($reachable->fresh(), $principal);

        // A draft Communication (excluded).
        $this->communications()->createDraft($principal, [
            'display_sender' => 'Rahai School', 'title' => 'Still draft', 'body' => 'x',
        ]);

        $insight = app(ZeroReachableCommunicationsInsightProvider::class)->build($this->scope());

        $this->assertSame(1, $insight->count);
        $this->assertSame([$publishedUnreachable->id], $insight->sourceIds);
    }

    // ============================================================ Provider F

    public function test_staff_without_category_counts_only_active_uncategorized(): void
    {
        $withCategory = $this->teacherStaff('sarah');
        $withCategory->update(['staff_category_id' => StaffCategory::first()->id, 'status' => 'active']);

        $withoutCategory = $this->teacherStaff('eka');
        $withoutCategory->update(['staff_category_id' => null, 'status' => 'active']);

        $inactiveWithoutCategory = $this->teacherStaff('budi');
        $inactiveWithoutCategory->update(['staff_category_id' => null, 'status' => 'terminated']);

        $insight = app(StaffWithoutCategoryInsightProvider::class)->build($this->scope());

        $this->assertGreaterThanOrEqual(1, $insight->count);
        $this->assertContains($withoutCategory->id, $insight->sourceIds);
        $this->assertNotContains($withCategory->id, $insight->sourceIds);
        $this->assertNotContains($inactiveWithoutCategory->id, $insight->sourceIds);
    }

    // ============================================================ Provider G

    public function test_academic_record_publication_needs_a_period_or_reports_unavailable(): void
    {
        $insight = app(AcademicRecordPublicationInsightProvider::class)->build($this->scope(period: null));
        $this->assertSame(ManagementInsight::RELIABILITY_UNAVAILABLE, $insight->reliability);
        $this->assertNull($insight->count);
    }

    public function test_academic_record_publication_refuses_a_period_that_has_not_ended(): void
    {
        $futurePeriod = AcademicPeriod::create([
            'academic_year_id' => $this->year->id, 'name' => 'Future', 'sequence' => 99,
            'start_date' => now()->addYears(5)->toDateString(),
            'end_date' => now()->addYears(6)->toDateString(),
        ]);

        $insight = app(AcademicRecordPublicationInsightProvider::class)->build($this->scope($futurePeriod));

        $this->assertSame(ManagementInsight::RELIABILITY_UNAVAILABLE, $insight->reliability);
        $this->assertNull($insight->count);
    }

    public function test_academic_record_publication_counts_active_students_without_a_published_record_for_a_past_period(): void
    {
        $pastPeriod = AcademicPeriod::create([
            'academic_year_id' => $this->year->id, 'name' => 'Past', 'sequence' => 98,
            'start_date' => '2020-01-01', 'end_date' => '2020-06-30',
        ]);

        $publishedStudent = $this->studentNamed('Evelyn', 'Wijaya', 'STU-1');
        $missingStudent = $this->studentNamed('Ratna', 'Yasin', 'STU-2');
        $inactiveStudent = $this->studentNamed('Old', 'Grad', 'STU-3');
        $inactiveStudent->update(['status' => 'graduated']);

        // A published record for one student.
        $record = AcademicRecord::create([
            'student_id' => $publishedStudent->id,
            'academic_year_id' => $this->year->id,
            'academic_period_id' => $pastPeriod->id,
            'student_name_snapshot' => 'Evelyn Wijaya', 'student_number_snapshot' => 'STU-1',
            'class_name_snapshot' => 'Year 5A', 'grade_name_snapshot' => 'Year 5',
            'period_name_snapshot' => 'Past', 'academic_year_name_snapshot' => '2026/2027',
            'school_name_snapshot' => 'Rahai', 'school_address_snapshot' => 'x',
            'principal_name_snapshot' => 'p',
            'homeroom_teacher_name_snapshot' => 'h',
            'homeroom_comment' => 'ok', 'notes' => 'ok',
            'status' => 'published', 'published_at' => now(),
            'published_by_user_id' => $this->principalUser()->id,
        ]);

        // A draft-only record for the missing student -- still counts as missing.
        AcademicRecord::create([
            'student_id' => $missingStudent->id,
            'academic_year_id' => $this->year->id,
            'academic_period_id' => $pastPeriod->id,
            'student_name_snapshot' => 'Ratna Yasin', 'student_number_snapshot' => 'STU-2',
            'class_name_snapshot' => 'Year 5A', 'grade_name_snapshot' => 'Year 5',
            'period_name_snapshot' => 'Past', 'academic_year_name_snapshot' => '2026/2027',
            'school_name_snapshot' => 'Rahai', 'school_address_snapshot' => 'x',
            'principal_name_snapshot' => 'p',
            'homeroom_teacher_name_snapshot' => 'h',
            'homeroom_comment' => 'ok', 'notes' => 'ok',
            'status' => 'draft',
        ]);

        $insight = app(AcademicRecordPublicationInsightProvider::class)->build($this->scope($pastPeriod));

        $this->assertSame(ManagementInsight::RELIABILITY_RELIABLE, $insight->reliability);
        $this->assertContains($missingStudent->id, $insight->sourceIds);
        $this->assertNotContains($publishedStudent->id, $insight->sourceIds);
        $this->assertNotContains($inactiveStudent->id, $insight->sourceIds);
    }

    // ==================================================== unavailable != zero

    public function test_an_unavailable_insight_may_not_be_constructed_with_a_non_null_count(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ManagementInsight(
            key: 'x', category: 'y', severity: 'info', title: 't', description: 'd',
            count: 0, // <-- this must be rejected
            reliability: ManagementInsight::RELIABILITY_UNAVAILABLE,
        );
    }

    // ------------------------------------------------------------- helpers

    private function activeFramework(): PerformanceFramework
    {
        $framework = PerformanceFramework::create([
            'code' => 'FR-1', 'name' => 'Framework 1', 'version' => '1',
            'staff_category_id' => StaffCategory::first()->id,
            'evidence_configuration' => [],
            'status' => 'draft', 'effective_from' => '2026-01-01',
        ]);
        $framework->update(['status' => 'active']);

        return $framework->fresh();
    }

    private function evaluationAttributes(PerformanceFramework $framework, array $overrides = []): array
    {
        $staffId = $overrides['staff_id'] ?? $this->teacherStaff('sarah')->id;
        $staff = Staff::findOrFail($staffId);
        $staff->update(['staff_category_id' => $framework->staff_category_id]);

        return $overrides + [
            'staff_id' => $staffId,
            'staff_category_id' => $framework->staff_category_id,
            'performance_framework_id' => $framework->id,
            'evaluator_user_id' => $this->principalUser()->id,
            'academic_year_id' => $this->year->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-06-30',
            'status' => 'draft',
            'summary' => 'x', 'strengths' => 'x', 'development_priorities' => 'x', 'action_plan' => 'x',
            'staff_name_snapshot' => $staff->fullName(),
            'staff_number_snapshot' => $staff->staff_number,
            'position_title_snapshot' => 'x',
            'staff_category_name_snapshot' => 'x',
            'framework_name_snapshot' => $framework->name,
            'framework_code_snapshot' => $framework->code,
            'framework_version_snapshot' => $framework->version,
            'evaluator_name_snapshot' => 'Principal',
        ];
    }
}
