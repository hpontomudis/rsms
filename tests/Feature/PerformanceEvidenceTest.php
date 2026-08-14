<?php

namespace Tests\Feature;

use App\Evidence\EvidenceRegistry;
use App\Evidence\EvidenceService;
use App\Models\Assessment;
use App\Models\DailyJournal;
use App\Models\User;
use App\Services\DailyJournalService;
use App\Services\SemesterProgrammeService;
use App\Services\TeachingModuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\Feature\Concerns\BuildsPerformanceFixtures;
use Tests\Feature\Concerns\BuildsPlanningFixtures;
use Tests\TestCase;

/**
 * The 8 evidence providers, computed directly (not through an Evaluation).
 *
 * "Available at zero" and "unavailable" must never be confused -- a teacher
 * with genuinely no modules yet is different from a teacher whose evidence
 * cannot be attributed at all. Each provider is checked for both.
 */
class PerformanceEvidenceTest extends TestCase
{
    use BuildsPerformanceFixtures, BuildsPlanningFixtures {
        BuildsPerformanceFixtures::userWithRole insteadof BuildsPlanningFixtures;
        BuildsPerformanceFixtures::auditCount insteadof BuildsPlanningFixtures;
    }
    use RefreshDatabase;

    private function evidence(): EvidenceService
    {
        return app(EvidenceService::class);
    }

    private function fullSetup(): void
    {
        $this->seedReferenceData();
        $this->seedPerformanceReferenceData();
    }

    private function start(): Carbon
    {
        return Carbon::parse('2026-07-01');
    }

    private function end(): Carbon
    {
        return Carbon::parse('2026-12-31');
    }

    /** A scope whose curriculum is left ACTIVE, ready to plan against. */
    private function activeScope(string $phaseCode): \App\Models\CurriculumScope
    {
        $scope = $this->scopeFor($phaseCode);
        $this->restoreActive($this->curriculum());

        return $scope;
    }

    // --------------------------------------------------------------- registry

    public function test_the_registry_recognises_exactly_the_eight_shipped_keys(): void
    {
        $this->assertCount(8, EvidenceRegistry::KEYS);
        foreach (EvidenceRegistry::KEYS as $key) {
            $this->assertTrue(EvidenceRegistry::has($key));
        }
        $this->assertFalse(EvidenceRegistry::has('made_up_key'));
    }

    public function test_computing_an_unregistered_key_throws(): void
    {
        $this->fullSetup();
        $staff = $this->staff('sarah');

        $this->expectException(InvalidArgumentException::class);
        $this->evidence()->compute('made_up_key', $staff, $this->start(), $this->end());
    }

    public function test_an_indicator_with_no_evidence_key_returns_null_from_forindicator(): void
    {
        $this->fullSetup();
        $frameworks = app(\App\Services\PerformanceFrameworkService::class);
        $framework = $frameworks->create($this->teacherCategory(), ['name' => 'X', 'code' => 'NOEV', 'version' => '1']);
        $section = $frameworks->addSection($framework, ['name' => 'S']);
        $narrative = $frameworks->addIndicator($section, ['name' => 'N', 'indicator_type' => 'narrative']);

        $this->assertNull($this->evidence()->forIndicator($narrative->fresh(), $this->staff('sarah'), $this->start(), $this->end()));
    }

    // -------------------------------------------------------- module / journal

    public function test_teaching_module_count_is_available_even_at_zero(): void
    {
        $this->fullSetup();
        $staff = $this->staff('sarah');

        $result = $this->evidence()->compute('teaching_module_count', $staff, $this->start(), $this->end());

        $this->assertTrue($result->isAvailable());
        $this->assertSame(0.0, $result->numericValue);
    }

    public function test_teaching_module_count_counts_only_this_staff_members_modules_in_range(): void
    {
        $this->fullSetup();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $otherAssignment = $this->assignmentFor('Year 5A', 'Eng', 'eka');
        $modules = app(TeachingModuleService::class);

        $modules->create($assignment, $this->activeScope('C'), ['title' => 'A', 'planned_activity' => 'x']);
        $modules->create($assignment, $this->activeScope('C'), ['title' => 'B', 'planned_activity' => 'x']);
        $modules->create($otherAssignment, $this->activeScope('C'), ['title' => 'C', 'planned_activity' => 'x']);

        $result = $this->evidence()->compute('teaching_module_count', $staff = $this->staff('sarah'), $this->start(), $this->end());

        $this->assertSame(2.0, $result->numericValue);
    }

    public function test_daily_journal_count_counts_by_assignment_responsibility(): void
    {
        $this->fullSetup();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $module = app(TeachingModuleService::class)->create($assignment, $this->activeScope('C'), ['title' => 'A', 'planned_activity' => 'x']);
        $journals = app(DailyJournalService::class);

        $journals->create($assignment, $this->activeScope('C'), $this->period('Semester 1'), '2026-08-01', $this->staff('sarah'), [
            'topic' => 'T1', 'actual_activity' => 'Did stuff.',
        ]);

        $result = $this->evidence()->compute('daily_journal_count', $this->staff('sarah'), $this->start(), $this->end());

        $this->assertSame(1.0, $result->numericValue);
    }

    public function test_journal_conducted_count_credits_whoever_actually_conducted_it_not_the_assignment_holder(): void
    {
        $this->fullSetup();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $substitute = $this->staff('eka');
        $journals = app(DailyJournalService::class);

        // sarah owns the assignment, but eka actually conducted the session.
        $journals->create($assignment, $this->activeScope('C'), $this->period('Semester 1'), '2026-08-01', $substitute, [
            'topic' => 'T1', 'actual_activity' => 'Covered a class.',
        ]);

        $sarahConducted = $this->evidence()->compute('journal_conducted_count', $this->staff('sarah'), $this->start(), $this->end());
        $ekaConducted = $this->evidence()->compute('journal_conducted_count', $substitute, $this->start(), $this->end());

        $this->assertSame(0.0, $sarahConducted->numericValue, 'sarah owns the slot but did not conduct it');
        $this->assertSame(1.0, $ekaConducted->numericValue, 'eka is the one who was actually there');
    }

    public function test_assessment_count_is_available_even_at_zero(): void
    {
        $this->fullSetup();
        $result = $this->evidence()->compute('assessment_count', $this->staff('sarah'), $this->start(), $this->end());

        $this->assertTrue($result->isAvailable());
        $this->assertSame(0.0, $result->numericValue);
    }

    public function test_assessment_count_counts_assessments_within_range(): void
    {
        $this->fullSetup();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        Assessment::create([
            'class_subject_id' => $assignment->id, 'academic_period_id' => $this->period('Semester 1')->id,
            'name' => 'Test 1', 'max_score' => 100, 'assessment_date' => '2026-08-01',
        ]);

        $result = $this->evidence()->compute('assessment_count', $this->staff('sarah'), $this->start(), $this->end());

        $this->assertSame(1.0, $result->numericValue);
    }

    // ---------------------------------------------------------------- context

    public function test_annual_programme_context_is_unavailable_with_no_overlapping_assignment(): void
    {
        $this->fullSetup();
        $result = $this->evidence()->compute('annual_programme_context', $this->staff('sarah'), $this->start(), $this->end());

        $this->assertFalse($result->isAvailable());
    }

    public function test_annual_programme_context_reports_the_roster_plan_not_authorship(): void
    {
        $this->fullSetup();
        $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $this->activatedClassProgramme();

        $result = $this->evidence()->compute('annual_programme_context', $this->staff('sarah'), $this->start(), $this->end());

        $this->assertTrue($result->isAvailable());
        $this->assertSame(1.0, $result->numericValue);
    }

    public function test_semester_programme_context_is_available_at_zero_when_an_assignment_exists_but_nothing_is_scheduled_yet(): void
    {
        $this->fullSetup();
        $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $this->activatedClassProgramme();

        $result = $this->evidence()->compute('semester_programme_context', $this->staff('sarah'), $this->start(), $this->end());

        $this->assertTrue($result->isAvailable(), 'an assignment exists, so this is a real zero, not an unknown');
        $this->assertSame(0.0, $result->numericValue);
    }

    public function test_semester_programme_context_is_unavailable_with_no_overlapping_assignment_at_all(): void
    {
        $this->fullSetup();

        $result = $this->evidence()->compute('semester_programme_context', $this->staff('sarah'), $this->start(), $this->end());

        $this->assertFalse($result->isAvailable());
    }

    public function test_semester_programme_context_is_available_once_one_is_scheduled(): void
    {
        $this->fullSetup();
        $this->scheduledProgramme();
        $this->teacherFor('Year 5A', 'Maths', 'sarah');

        $result = $this->evidence()->compute('semester_programme_context', $this->staff('sarah'), $this->start(), $this->end());

        $this->assertTrue($result->isAvailable());
    }

    // ------------------------------------------------------------ contribution

    public function test_annual_programme_contribution_is_unavailable_with_no_linked_login(): void
    {
        $this->fullSetup();
        $staff = $this->staffInCategory($this->teacherCategory()); // no user_id

        $result = $this->evidence()->compute('annual_programme_contribution', $staff, $this->start(), $this->end());

        $this->assertFalse($result->isAvailable());
        $this->assertStringContainsString('no linked login', $result->note);
    }

    public function test_annual_programme_contribution_is_unavailable_with_a_shared_login(): void
    {
        $this->fullSetup();
        $user = User::factory()->create();
        $this->staffInCategory($this->teacherCategory(), $user->id);
        $this->staffInCategory($this->teacherCategory(), $user->id);
        $sharing = \App\Models\Staff::where('user_id', $user->id)->first();

        $result = $this->evidence()->compute('annual_programme_contribution', $sharing, $this->start(), $this->end());

        $this->assertFalse($result->isAvailable());
        $this->assertStringContainsString('shared by more than one', $result->note);
    }

    public function test_annual_programme_contribution_counts_edits_attributed_to_the_staff_members_own_login(): void
    {
        $this->fullSetup();
        $sarah = $this->staff('sarah');
        $this->assignmentFor('Year 5A', 'Maths', 'sarah');

        $this->actingAs($sarah->user);
        $this->classProgramme();

        $result = $this->evidence()->compute('annual_programme_contribution', $sarah, $this->start(), $this->end());

        $this->assertTrue($result->isAvailable());
        $this->assertGreaterThanOrEqual(1.0, $result->numericValue);
    }

    public function test_semester_programme_contribution_counts_edits_attributed_to_the_staff_members_own_login(): void
    {
        $this->fullSetup();
        $sarah = $this->staff('sarah');
        [$annual, $semester] = $this->scheduledProgramme();
        $this->teacherFor('Year 5A', 'Maths', 'sarah');

        $this->actingAs($sarah->user);
        app(SemesterProgrammeService::class)->addSlot($semester, $annual->items()->first(), [
            'week_label' => 'Week 2', 'planned_lesson_periods' => 8,
        ]);

        $result = $this->evidence()->compute('semester_programme_contribution', $sarah, $this->start(), $this->end());

        $this->assertTrue($result->isAvailable());
        $this->assertGreaterThanOrEqual(1.0, $result->numericValue);
    }
}
