<?php

namespace Tests\Feature;

use App\Models\PerformanceEvaluation;
use App\Models\PerformanceEvidence;
use App\Models\Staff;
use App\Models\User;
use App\Services\PerformanceEvaluationItemService;
use App\Services\PerformanceEvaluationService;
use App\Services\PerformanceFrameworkService;
use Database\Seeders\PositionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Feature\Concerns\BuildsPerformanceFixtures;
use Tests\TestCase;

/**
 * Creating and finalizing a Performance Evaluation.
 *
 * The tests worth reading closely are the finalize() ones: they prove the
 * evidence/rating firewall (system evidence never sets a response), that
 * finalize() snapshots LIVE data at the moment it runs (not whatever a draft
 * last displayed), that the snapshot then survives every kind of upstream
 * mutation, and that "finalized" really does mean immutable with no
 * correction path.
 */
class PerformanceEvaluationTest extends TestCase
{
    use BuildsPerformanceFixtures;
    use RefreshDatabase;

    private function evaluations(): PerformanceEvaluationService
    {
        return app(PerformanceEvaluationService::class);
    }

    private function items(): PerformanceEvaluationItemService
    {
        return app(PerformanceEvaluationItemService::class);
    }

    private function evaluator(): User
    {
        return $this->userWithRole('principal');
    }

    // ------------------------------------------------------------ creation

    public function test_a_staff_member_with_no_category_cannot_be_evaluated(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework] = $this->activeFramework();
        $staff = Staff::create([
            'staff_number' => 'NC', 'first_name' => 'No', 'last_name' => 'Category',
            'position_id' => \App\Models\Position::firstOrFail()->id, 'phone' => '08',
            'hire_date' => '2020-01-01', 'status' => 'active',
        ]);

        $this->expectException(ValidationException::class);
        $this->evaluations()->create($staff, $framework, $this->evaluator(), '2026-01-01', '2026-06-30');
    }

    public function test_the_framework_must_be_active(): void
    {
        $this->seedPerformanceReferenceData();
        $draftFramework = app(PerformanceFrameworkService::class)
            ->create($this->teacherCategory(), ['name' => 'X', 'code' => 'DRAFT', 'version' => '1']);
        $staff = $this->staffInCategory($this->teacherCategory());

        $this->expectException(ValidationException::class);
        $this->evaluations()->create($staff, $draftFramework, $this->evaluator(), '2026-01-01', '2026-06-30');
    }

    public function test_the_framework_must_match_the_staff_members_category(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework] = $this->activeFramework();
        $driver = $this->staffInCategory(\App\Models\StaffCategory::where('code', 'driver')->firstOrFail());

        $this->expectException(ValidationException::class);
        $this->evaluations()->create($driver, $framework, $this->evaluator(), '2026-01-01', '2026-06-30');
    }

    public function test_the_period_must_not_end_before_it_starts(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework] = $this->activeFramework();
        $staff = $this->staffInCategory($framework->staffCategory);

        $this->expectException(ValidationException::class);
        $this->evaluations()->create($staff, $framework, $this->evaluator(), '2026-06-30', '2026-01-01');
    }

    public function test_an_exact_scope_duplicate_is_refused(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework] = $this->activeFramework();
        $staff = $this->staffInCategory($framework->staffCategory);
        $this->evaluations()->create($staff, $framework, $this->evaluator(), '2026-01-01', '2026-06-30');

        $this->expectException(ValidationException::class);
        $this->evaluations()->create($staff, $framework, $this->evaluator(), '2026-01-01', '2026-06-30');
    }

    public function test_a_different_period_for_the_same_staff_and_framework_is_allowed(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework] = $this->activeFramework();
        $staff = $this->staffInCategory($framework->staffCategory);
        $this->evaluations()->create($staff, $framework, $this->evaluator(), '2026-01-01', '2026-06-30');

        $second = $this->evaluations()->create($staff, $framework, $this->evaluator(), '2026-07-01', '2026-12-31');

        $this->assertSame(2, PerformanceEvaluation::where('staff_id', $staff->id)->count());
        $this->assertNotSame($second->id, null);
    }

    public function test_one_item_is_auto_provisioned_per_indicator(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework] = $this->activeFramework();
        $staff = $this->staffInCategory($framework->staffCategory);

        $evaluation = $this->evaluations()->create($staff, $framework, $this->evaluator(), '2026-01-01', '2026-06-30');

        $this->assertSame(2, $evaluation->items()->count());
    }

    public function test_the_evaluation_copies_the_staff_members_category_at_creation(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework] = $this->activeFramework();
        $staff = $this->staffInCategory($framework->staffCategory);

        $evaluation = $this->evaluations()->create($staff, $framework, $this->evaluator(), '2026-01-01', '2026-06-30');

        $this->assertSame($framework->staff_category_id, $evaluation->staff_category_id);

        // Re-categorizing the staff member afterwards does not move the evaluation.
        $other = \App\Models\StaffCategory::where('code', 'driver')->firstOrFail();
        $staff->update(['staff_category_id' => $other->id]);

        $this->assertSame($framework->staff_category_id, $evaluation->fresh()->staff_category_id);
    }

    public function test_a_period_outside_the_given_academic_year_is_refused(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework] = $this->activeFramework();
        $staff = $this->staffInCategory($framework->staffCategory);
        $year = \App\Models\AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30',
        ]);

        $this->expectException(ValidationException::class);
        $this->evaluations()->create($staff, $framework, $this->evaluator(), '2026-01-01', '2026-06-30', $year);
    }

    // --------------------------------------------------- response firewall

    public function test_each_indicator_type_accepts_only_its_own_field(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework, $rubric, $numeric, $high] = $this->activeFramework();
        $staff = $this->staffInCategory($framework->staffCategory);
        $evaluation = $this->evaluations()->create($staff, $framework, $this->evaluator(), '2026-01-01', '2026-06-30');
        $rubricItem = $evaluation->items()->where('performance_indicator_id', $rubric->id)->firstOrFail();

        $this->items()->respond($rubricItem, ['rating_option_id' => $high->id]);
        $this->assertSame($high->id, $rubricItem->fresh()->rating_option_id);

        try {
            $this->items()->respond($rubricItem, ['numeric_value' => 5]);
            $this->fail('a rubric indicator accepted a numeric_value');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('does not accept', $e->errors()['numeric_value'][0]);
        }
    }

    public function test_a_rating_option_must_belong_to_the_items_own_framework(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework, $rubric] = $this->activeFramework($this->teacherCategory(), 'FW1', '1');
        [$otherFramework, , , $otherHigh] = $this->activeFramework($this->teacherCategory(), 'FW2', '1');
        $staff = $this->staffInCategory($framework->staffCategory);
        $evaluation = $this->evaluations()->create($staff, $framework, $this->evaluator(), '2026-01-01', '2026-06-30');
        $rubricItem = $evaluation->items()->where('performance_indicator_id', $rubric->id)->firstOrFail();

        $this->expectException(ValidationException::class);
        $this->items()->respond($rubricItem, ['rating_option_id' => $otherHigh->id]);
    }

    public function test_narrative_and_boolean_indicators_write_their_own_field(): void
    {
        $this->seedPerformanceReferenceData();
        $frameworks = app(PerformanceFrameworkService::class);
        $framework = $frameworks->create($this->teacherCategory(), ['name' => 'X', 'code' => 'X', 'version' => '1']);
        $section = $frameworks->addSection($framework, ['name' => 'S']);
        $narrative = $frameworks->addIndicator($section, ['name' => 'N', 'indicator_type' => 'narrative']);
        $boolean = $frameworks->addIndicator($section, ['name' => 'B', 'indicator_type' => 'boolean']);
        $frameworks->addRatingOption($framework, ['value' => 1, 'label' => 'A']);
        $framework = $frameworks->activate($framework->fresh());

        $staff = $this->staffInCategory($framework->staffCategory);
        $evaluation = $this->evaluations()->create($staff, $framework, $this->evaluator(), '2026-01-01', '2026-06-30');

        $narrativeItem = $evaluation->items()->where('performance_indicator_id', $narrative->fresh()->id)->firstOrFail();
        $booleanItem = $evaluation->items()->where('performance_indicator_id', $boolean->fresh()->id)->firstOrFail();

        $this->items()->respond($narrativeItem, ['narrative_response' => 'Good.']);
        $this->items()->respond($booleanItem, ['boolean_value' => false]);

        $this->assertSame('Good.', $narrativeItem->fresh()->narrative_response);
        $this->assertFalse($booleanItem->fresh()->boolean_value);
    }

    public function test_manual_evidence_requires_a_note(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework, $rubric] = $this->activeFramework();
        $staff = $this->staffInCategory($framework->staffCategory);
        $evaluation = $this->evaluations()->create($staff, $framework, $this->evaluator(), '2026-01-01', '2026-06-30');
        $item = $evaluation->items()->where('performance_indicator_id', $rubric->id)->firstOrFail();

        $this->expectException(ValidationException::class);
        $this->items()->addManualEvidence($item, 'Observation', '');
    }

    public function test_manual_evidence_can_be_added_and_removed_while_draft(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework, $rubric] = $this->activeFramework();
        $staff = $this->staffInCategory($framework->staffCategory);
        $evaluation = $this->evaluations()->create($staff, $framework, $this->evaluator(), '2026-01-01', '2026-06-30');
        $item = $evaluation->items()->where('performance_indicator_id', $rubric->id)->firstOrFail();

        $evidence = $this->items()->addManualEvidence($item, 'Observation', 'Saw a good lesson.');
        $this->assertTrue($evidence->isManual());
        $this->assertTrue($evidence->isAvailable());

        $this->items()->removeManualEvidence($evidence);
        $this->assertSame(0, PerformanceEvidence::count());
    }

    public function test_system_evidence_can_never_be_removed_by_a_human(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework, $rubric] = $this->activeFramework();
        $staff = $this->staffInCategory($framework->staffCategory);
        $evaluation = $this->evaluations()->create($staff, $framework, $this->evaluator(), '2026-01-01', '2026-06-30');
        $item = $evaluation->items()->where('performance_indicator_id', $rubric->id)->firstOrFail();
        $systemRow = PerformanceEvidence::create([
            'performance_evaluation_item_id' => $item->id, 'source_type' => 'system', 'source_key' => 'x',
            'source_label' => 'x', 'availability' => 'available', 'captured_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        $this->items()->removeManualEvidence($systemRow);
    }

    // -------------------------------------------------------------- draft

    public function test_overall_rating_must_belong_to_the_evaluations_framework(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework] = $this->activeFramework($this->teacherCategory(), 'FW1', '1');
        [, , , $otherHigh] = $this->activeFramework($this->teacherCategory(), 'FW2', '1');
        $staff = $this->staffInCategory($framework->staffCategory);
        $evaluation = $this->evaluations()->create($staff, $framework, $this->evaluator(), '2026-01-01', '2026-06-30');

        $this->expectException(ValidationException::class);
        $this->evaluations()->setOverallRating($evaluation, $otherHigh);
    }

    public function test_a_draft_deletes_with_its_items_and_evidence(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework, $rubric] = $this->activeFramework();
        $staff = $this->staffInCategory($framework->staffCategory);
        $evaluation = $this->evaluations()->create($staff, $framework, $this->evaluator(), '2026-01-01', '2026-06-30');
        $item = $evaluation->items()->where('performance_indicator_id', $rubric->id)->firstOrFail();
        $this->items()->addManualEvidence($item, 'Observation', 'Note.');

        $this->evaluations()->deleteDraft($evaluation);

        $this->assertSame(0, PerformanceEvaluation::count());
        $this->assertSame(0, \App\Models\PerformanceEvaluationItem::count());
        $this->assertSame(0, PerformanceEvidence::count());
    }

    // --------------------------------------------------------- finalize()

    public function test_finalize_refuses_an_unanswered_indicator(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework, $rubric, , $high] = $this->activeFramework();
        $staff = $this->staffInCategory($framework->staffCategory);
        $evaluation = $this->evaluations()->create($staff, $framework, $this->evaluator(), '2026-01-01', '2026-06-30');
        $item = $evaluation->items()->where('performance_indicator_id', $rubric->id)->firstOrFail();
        $this->items()->respond($item, ['rating_option_id' => $high->id]);
        // The numeric indicator is left unanswered.
        $this->evaluations()->setOverallRating($evaluation, $high);

        try {
            $this->evaluations()->finalize($evaluation, $this->evaluator());
            $this->fail('finalized with an unanswered indicator');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('no response', $e->errors()['status'][0]);
        }
    }

    public function test_finalize_requires_an_overall_rating(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework, $rubric, $numeric, $high] = $this->activeFramework();
        $staff = $this->staffInCategory($framework->staffCategory);
        $evaluation = $this->evaluations()->create($staff, $framework, $this->evaluator(), '2026-01-01', '2026-06-30');
        $this->respondToEveryItem($evaluation, $high->id);

        try {
            $this->evaluations()->finalize($evaluation, $this->evaluator());
            $this->fail('finalized without an overall rating');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('overall_rating_option_id', $e->errors());
        }
    }

    public function test_finalize_snapshots_the_header_and_flips_status(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework, , , $high] = $this->activeFramework();
        $staff = $this->staffInCategory($framework->staffCategory);
        $evaluator = $this->evaluator();
        $evaluation = $this->evaluations()->create($staff, $framework, $evaluator, '2026-01-01', '2026-06-30');
        $this->respondToEveryItem($evaluation, $high->id);
        $this->evaluations()->setOverallRating($evaluation, $high);

        $finalized = $this->evaluations()->finalize($evaluation, $evaluator);

        $this->assertTrue($finalized->isFinalized());
        $this->assertNotNull($finalized->finalized_at);
        $this->assertSame($evaluator->id, $finalized->finalized_by_user_id);
        $this->assertSame($staff->fullName(), $finalized->staff_name_snapshot);
        $this->assertSame($staff->staff_number, $finalized->staff_number_snapshot);
        $this->assertSame($framework->staffCategory->name, $finalized->staff_category_name_snapshot);
        $this->assertSame($framework->name, $finalized->framework_name_snapshot);
        $this->assertSame($framework->code, $finalized->framework_code_snapshot);
        $this->assertSame($framework->version, $finalized->framework_version_snapshot);
        $this->assertSame($evaluator->name, $finalized->evaluator_name_snapshot);
    }

    public function test_finalize_snapshots_every_item(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework, $rubric] = $this->activeFramework();
        $staff = $this->staffInCategory($framework->staffCategory);
        $evaluator = $this->evaluator();
        $evaluation = $this->evaluations()->create($staff, $framework, $evaluator, '2026-01-01', '2026-06-30');
        $this->respondToEveryItem($evaluation, $rubric->section->framework->ratingOptions()->where('value', 4)->firstOrFail()->id);
        $this->evaluations()->setOverallRating($evaluation, $rubric->section->framework->ratingOptions()->where('value', 4)->firstOrFail());

        $this->evaluations()->finalize($evaluation, $evaluator);

        $item = $evaluation->items()->where('performance_indicator_id', $rubric->id)->firstOrFail();
        $this->assertSame('Planning', $item->section_name_snapshot);
        $this->assertSame($rubric->name, $item->indicator_name_snapshot);
        $this->assertSame('rubric', $item->indicator_type_snapshot);
        $this->assertSame('Highly Effective', $item->rating_label_snapshot);
        $this->assertSame(4, $item->rating_value_snapshot);
    }

    public function test_finalize_recomputes_and_snapshots_live_system_evidence(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework, $rubric, $numeric, $high] = $this->activeFramework();
        $staff = $this->staffInCategory($framework->staffCategory);
        $evaluator = $this->evaluator();
        $evaluation = $this->evaluations()->create($staff, $framework, $evaluator, '2026-01-01', '2026-06-30');
        $this->respondToEveryItem($evaluation, $high->id);
        $this->evaluations()->setOverallRating($evaluation, $high);

        $this->evaluations()->finalize($evaluation, $evaluator);

        $rubricItem = $evaluation->items()->where('performance_indicator_id', $rubric->id)->firstOrFail();
        $numericItem = $evaluation->items()->where('performance_indicator_id', $numeric->id)->firstOrFail();

        // Rubric's evidence key (annual_programme_context) has no overlapping
        // assignment for this staff member, so it is correctly unavailable --
        // never a silent zero.
        $rubricEvidence = $rubricItem->evidence()->where('source_type', 'system')->firstOrFail();
        $this->assertSame('unavailable', $rubricEvidence->availability);

        // Numeric's evidence key (teaching_module_count) is always available,
        // even at zero.
        $numericEvidence = $numericItem->evidence()->where('source_type', 'system')->firstOrFail();
        $this->assertSame('available', $numericEvidence->availability);
        $this->assertEquals(0, $numericEvidence->numeric_value);
    }

    public function test_manual_evidence_survives_finalize_untouched(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework, $rubric, , $high] = $this->activeFramework();
        $staff = $this->staffInCategory($framework->staffCategory);
        $evaluator = $this->evaluator();
        $evaluation = $this->evaluations()->create($staff, $framework, $evaluator, '2026-01-01', '2026-06-30');
        $item = $evaluation->items()->where('performance_indicator_id', $rubric->id)->firstOrFail();
        $manual = $this->items()->addManualEvidence($item, 'Observation', 'Saw a great lesson.');
        $this->respondToEveryItem($evaluation, $high->id);
        $this->evaluations()->setOverallRating($evaluation, $high);

        $this->evaluations()->finalize($evaluation, $evaluator);

        $this->assertSame('Saw a great lesson.', $manual->fresh()->note);
        $this->assertTrue($manual->fresh()->isManual());
    }

    public function test_the_rating_and_evidence_firewall_holds_through_finalize(): void
    {
        // No provider, no service method anywhere computes a rating from
        // evidence. This is the structural proof: an indicator whose only
        // evidence is UNAVAILABLE still finalizes fine off the human response
        // alone, and the human response is never touched by finalize().
        $this->seedPerformanceReferenceData();
        [$framework, $rubric, , , $low] = $this->activeFramework();
        $staff = $this->staffInCategory($framework->staffCategory);
        $evaluator = $this->evaluator();
        $evaluation = $this->evaluations()->create($staff, $framework, $evaluator, '2026-01-01', '2026-06-30');
        $this->respondToEveryItem($evaluation, $low->id);
        $this->evaluations()->setOverallRating($evaluation, $low);

        $finalized = $this->evaluations()->finalize($evaluation, $evaluator);

        $item = $finalized->items()->where('performance_indicator_id', $rubric->id)->firstOrFail();
        $this->assertSame($low->id, $item->rating_option_id, 'the human choice, unmoved by unavailable evidence');
        $this->assertSame('Needs Improvement', $item->rating_label_snapshot);
    }

    public function test_an_archived_framework_does_not_block_finalizing_an_in_flight_evaluation(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework, , , $high] = $this->activeFramework();
        $staff = $this->staffInCategory($framework->staffCategory);
        $evaluator = $this->evaluator();
        $evaluation = $this->evaluations()->create($staff, $framework, $evaluator, '2026-01-01', '2026-06-30');
        $this->respondToEveryItem($evaluation, $high->id);
        $this->evaluations()->setOverallRating($evaluation, $high);

        app(PerformanceFrameworkService::class)->archive($framework->fresh());

        $finalized = $this->evaluations()->finalize($evaluation, $evaluator);

        $this->assertTrue($finalized->isFinalized());
    }

    public function test_finalized_snapshots_are_unaffected_by_later_upstream_mutation(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework, , , $high] = $this->activeFramework();
        $staff = $this->staffInCategory($framework->staffCategory);
        $evaluator = $this->evaluator();
        $evaluation = $this->evaluations()->create($staff, $framework, $evaluator, '2026-01-01', '2026-06-30');
        $this->respondToEveryItem($evaluation, $high->id);
        $this->evaluations()->setOverallRating($evaluation, $high);
        $finalized = $this->evaluations()->finalize($evaluation, $evaluator);

        $staff->update(['first_name' => 'Mutated']);

        $this->assertSame('Test Staff', $finalized->fresh()->staff_name_snapshot);
        $this->assertNotSame('Test Staff', $staff->fresh()->fullName());
    }

    public function test_a_finalized_evaluation_refuses_every_edit(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework, , , $high] = $this->activeFramework();
        $staff = $this->staffInCategory($framework->staffCategory);
        $evaluator = $this->evaluator();
        $evaluation = $this->evaluations()->create($staff, $framework, $evaluator, '2026-01-01', '2026-06-30');
        $this->respondToEveryItem($evaluation, $high->id);
        $this->evaluations()->setOverallRating($evaluation, $high);
        $finalized = $this->evaluations()->finalize($evaluation, $evaluator);

        $this->expectException(LogicException::class);
        $finalized->update(['summary' => 'tampering']);
    }

    public function test_a_finalized_items_response_is_immutable(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework, $rubric, , $high] = $this->activeFramework();
        $staff = $this->staffInCategory($framework->staffCategory);
        $evaluator = $this->evaluator();
        $evaluation = $this->evaluations()->create($staff, $framework, $evaluator, '2026-01-01', '2026-06-30');
        $this->respondToEveryItem($evaluation, $high->id);
        $this->evaluations()->setOverallRating($evaluation, $high);
        $this->evaluations()->finalize($evaluation, $evaluator);
        $item = $evaluation->items()->where('performance_indicator_id', $rubric->id)->firstOrFail();

        $this->expectException(ValidationException::class);
        $this->items()->respond($item, ['rating_option_id' => $high->id]);
    }

    public function test_a_finalized_evaluation_is_never_deleted(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework, , , $high] = $this->activeFramework();
        $staff = $this->staffInCategory($framework->staffCategory);
        $evaluator = $this->evaluator();
        $evaluation = $this->evaluations()->create($staff, $framework, $evaluator, '2026-01-01', '2026-06-30');
        $this->respondToEveryItem($evaluation, $high->id);
        $this->evaluations()->setOverallRating($evaluation, $high);
        $this->evaluations()->finalize($evaluation, $evaluator);

        $this->expectException(ValidationException::class);
        $this->evaluations()->deleteDraft($evaluation->fresh());
    }

    /**
     * Finalized is immutable and V7A has no correction/replacement workflow
     * for it -- confirmed by the write refusal below. A DIFFERENT period for
     * the same staff+framework is a genuinely separate evaluation, not a
     * workaround for the finalized one; the unique index on
     * staff+framework+exact period would refuse a same-scope duplicate.
     */
    public function test_finalized_is_immutable_and_a_different_period_is_a_separate_evaluation_not_a_correction(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework, , , $high, $low] = $this->activeFramework();
        $staff = $this->staffInCategory($framework->staffCategory);
        $evaluator = $this->evaluator();
        $evaluation = $this->evaluations()->create($staff, $framework, $evaluator, '2026-01-01', '2026-06-30');
        $this->respondToEveryItem($evaluation, $low->id);
        $this->evaluations()->setOverallRating($evaluation, $low);
        $this->evaluations()->finalize($evaluation, $evaluator);

        try {
            $this->evaluations()->setOverallRating($evaluation->fresh(), $high);
            $this->fail('a finalized evaluation accepted a changed overall rating');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('immutable', $e->errors()['status'][0]);
        }

        // A later, genuinely different reporting period -- not a same-scope
        // replacement. The finalized 2026 evaluation is untouched either way.
        $laterPeriod = $this->evaluations()->create($staff, $framework, $evaluator, '2027-01-01', '2027-06-30');
        $this->assertTrue($laterPeriod->isDraft());
        $this->assertSame('finalized', $evaluation->fresh()->status);
        $this->assertSame(2, PerformanceEvaluation::count());
    }

    // ---------------------------------------------------------------- audit

    public function test_finalization_is_audited(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework, , , $high] = $this->activeFramework();
        $staff = $this->staffInCategory($framework->staffCategory);
        $evaluator = $this->evaluator();
        $evaluation = $this->evaluations()->create($staff, $framework, $evaluator, '2026-01-01', '2026-06-30');
        $this->respondToEveryItem($evaluation, $high->id);
        $this->evaluations()->setOverallRating($evaluation, $high);

        $updated = $this->auditCount(PerformanceEvaluation::class, 'updated');

        $this->evaluations()->finalize($evaluation, $evaluator);

        $this->assertGreaterThan($updated, $this->auditCount(PerformanceEvaluation::class, 'updated'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PositionSeeder::class);
    }
}
