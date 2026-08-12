<?php

namespace Tests\Feature;

use App\Models\AnnualProgrammeItem;
use App\Models\SemesterProgramme;
use App\Models\SemesterProgrammeItem;
use App\Services\SemesterProgrammeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Feature\Concerns\BuildsPlanningFixtures;
use Tests\TestCase;

/**
 * The cross-layer invariant an ACTIVE plan must keep.
 *
 * Because an active Prota and an active Prosem both stay editable, neither can
 * be allowed to falsify the other. Every test here is an edit that WOULD have
 * left an active semester programme incomplete or unreconciled, and the state
 * is re-read afterwards to prove nothing was written.
 *
 * A draft plan is deliberately exempt -- it may be incomplete while it is being
 * prepared, and activation is the gate.
 */
class PlanningInvariantTest extends TestCase
{
    use BuildsPlanningFixtures;
    use RefreshDatabase;

    /**
     * The canonical fixture from the specification: one objective budgeted at
     * 8 JP, scheduled across three active slots of 2 + 2 + 4.
     *
     * @return array{0: AnnualProgrammeItem, 1: SemesterProgramme}
     */
    private function activePlanOfEight(): array
    {
        $annual = $this->classProgramme();
        $this->programmes()->addItem($annual, $this->pathwayItem(1), $this->period('Semester 1'), 8);
        $annual = $this->programmes()->activate($annual->fresh());

        $item = $annual->items()->first();
        $semester = $this->semesters()->create($annual, $this->period('Semester 1'));

        foreach ([['Minggu 1', 2], ['Minggu 2', 2], ['Minggu 5', 4]] as [$label, $jp]) {
            $this->semesters()->addSlot($semester, $item, ['week_label' => $label, 'planned_lesson_periods' => $jp]);
        }

        $this->semesters()->activate($semester->fresh());

        return [$item->fresh(), $semester->fresh()];
    }

    private function semesters(): SemesterProgrammeService
    {
        return app(SemesterProgrammeService::class);
    }

    /** @return array<int, int> slot id => JP, in schedule order */
    private function distribution(SemesterProgramme $semester): array
    {
        return $semester->items()->get()
            ->mapWithKeys(fn ($slot) => [$slot->id => $slot->planned_lesson_periods])->all();
    }

    // ------------------------------------------------------- the baseline

    public function test_the_active_plan_is_complete_before_anything_is_edited(): void
    {
        $this->seedReferenceData();
        [$item, $semester] = $this->activePlanOfEight();

        $this->assertTrue($semester->isActive());
        $this->assertSame(8, $item->planned_lesson_periods);
        $this->assertSame([2, 2, 4], array_values($this->distribution($semester)));

        // No exception: the invariant holds as written.
        $this->semesters()->assertPlanIsComplete($semester);
    }

    // ------------------------------------------- annual item membership

    public function test_adding_an_annual_item_to_a_period_with_an_active_schedule_is_refused(): void
    {
        $this->seedReferenceData();
        [$item, $semester] = $this->activePlanOfEight();
        $annual = $item->annualProgramme;

        try {
            $this->programmes()->addItem($annual, $this->pathwayItem(2), $this->period('Semester 1'), 6);
            $this->fail('a second objective was allowed into a period whose plan is in force');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('active Semester Programme', $e->errors()['academic_period_id'][0]);
            $this->assertStringContainsString('planning revision', $e->errors()['academic_period_id'][0]);
        }

        $this->assertSame(1, $annual->fresh()->items()->count());
        $this->semesters()->assertPlanIsComplete($semester->fresh());
    }

    public function test_adding_to_a_different_period_is_unaffected(): void
    {
        $this->seedReferenceData();
        [$item] = $this->activePlanOfEight();

        $this->programmes()->addItem($item->annualProgramme, $this->pathwayItem(2), $this->period('Semester 2'), 6);

        $this->assertSame(2, $item->annualProgramme->fresh()->items()->count());
    }

    public function test_adding_to_a_period_whose_schedule_is_still_draft_is_allowed(): void
    {
        $this->seedReferenceData();
        $annual = $this->activatedClassProgramme();
        $this->semesters()->create($annual, $this->period('Semester 1'));

        $this->programmes()->addItem($annual, $this->pathwayItem(2), $this->period('Semester 1'), 4);

        $this->assertSame(2, $annual->fresh()->items()->count());
    }

    public function test_removing_a_scheduled_annual_item_is_refused(): void
    {
        $this->seedReferenceData();
        [$item, $semester] = $this->activePlanOfEight();

        try {
            $this->programmes()->removeItem($item);
            $this->fail('a scheduled allocation was removed');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('scheduled in a semester programme', $e->errors()['items'][0]);
        }

        $this->assertModelExists($item);
        $this->semesters()->assertPlanIsComplete($semester->fresh());
    }

    public function test_the_database_refuses_the_delete_even_if_the_service_is_bypassed(): void
    {
        $this->seedReferenceData();
        [$item] = $this->activePlanOfEight();

        // RESTRICT is the backstop behind the readable service error.
        $this->expectException(\Illuminate\Database\QueryException::class);

        $item->delete();
    }

    public function test_an_unscheduled_annual_item_can_still_be_removed_from_a_draft_parent(): void
    {
        $this->seedReferenceData();
        $annual = $this->classProgramme();
        $item = $this->programmes()->addItem($annual, $this->pathwayItem(1), $this->period('Semester 1'), 8);

        $this->programmes()->removeItem($item);

        $this->assertSame(0, $annual->fresh()->items()->count());
    }

    public function test_an_unscheduled_annual_item_can_still_be_removed_from_an_active_parent(): void
    {
        $this->seedReferenceData();
        $annual = $this->activatedClassProgramme();
        $extra = $this->programmes()->addItem($annual, $this->pathwayItem(2), $this->period('Semester 2'), 4);

        $this->programmes()->removeItem($extra);

        $this->assertSame(1, $annual->fresh()->items()->count());
    }

    // -------------------------------------------------- moving an item

    public function test_moving_an_unscheduled_item_into_an_active_period_is_refused(): void
    {
        $this->seedReferenceData();
        [$item, $semester] = $this->activePlanOfEight();
        $annual = $item->annualProgramme;

        $stray = $this->programmes()->addItem($annual, $this->pathwayItem(2), $this->period('Semester 2'), 6);

        try {
            $this->programmes()->updateItem($stray, $this->period('Semester 1'));
            $this->fail('an item was moved into a period whose plan is in force');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('active Semester Programme', $e->errors()['academic_period_id'][0]);
        }

        $this->assertSame($this->period('Semester 2')->id, $stray->fresh()->academic_period_id);
        $this->semesters()->assertPlanIsComplete($semester->fresh());
    }

    public function test_moving_a_scheduled_item_out_of_its_period_is_still_refused(): void
    {
        $this->seedReferenceData();
        [$item, $semester] = $this->activePlanOfEight();

        try {
            $this->programmes()->updateItem($item, $this->period('Semester 2'));
            $this->fail('a scheduled item was moved out of its period');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already scheduled', $e->errors()['academic_period_id'][0]);
        }

        $this->assertSame($this->period('Semester 1')->id, $item->fresh()->academic_period_id);
        $this->semesters()->assertPlanIsComplete($semester->fresh());
    }

    // ------------------------------------------------ the annual budget

    public function test_raising_the_annual_budget_above_the_scheduled_total_is_refused(): void
    {
        $this->seedReferenceData();
        [$item, $semester] = $this->activePlanOfEight();

        try {
            $this->programmes()->updateItem($item, null, 10, touchBudget: true);
            $this->fail('the budget was raised past what the active schedule spends');
        } catch (ValidationException $e) {
            $message = $e->errors()['planned_lesson_periods'][0];
            $this->assertStringContainsString('scheduled for 8 JP', $message);
            $this->assertStringContainsString('10 JP', $message);
        }

        $this->assertSame(8, $item->fresh()->planned_lesson_periods);
        $this->semesters()->assertPlanIsComplete($semester->fresh());
    }

    public function test_setting_a_budget_from_null_is_allowed_when_the_slots_already_agree(): void
    {
        $this->seedReferenceData();
        $annual = $this->classProgramme();
        // No budget at all, so the slots reconcile against nothing.
        $item = $this->programmes()->addItem($annual, $this->pathwayItem(1), $this->period('Semester 1'));
        $annual = $this->programmes()->activate($annual->fresh());

        $semester = $this->semesters()->create($annual, $this->period('Semester 1'));
        $this->semesters()->addSlot($semester, $item, ['week_label' => 'Minggu 1', 'planned_lesson_periods' => 5]);
        $this->semesters()->addSlot($semester, $item, ['week_label' => 'Minggu 2', 'planned_lesson_periods' => 3]);
        $this->semesters()->activate($semester->fresh());

        $this->programmes()->updateItem($item->fresh(), null, 8, touchBudget: true);

        $this->assertSame(8, $item->fresh()->planned_lesson_periods);
        $this->semesters()->assertPlanIsComplete($semester->fresh());
    }

    public function test_setting_a_budget_from_null_is_refused_when_a_slot_carries_no_jp(): void
    {
        $this->seedReferenceData();
        $annual = $this->classProgramme();
        $item = $this->programmes()->addItem($annual, $this->pathwayItem(1), $this->period('Semester 1'));
        $annual = $this->programmes()->activate($annual->fresh());

        $semester = $this->semesters()->create($annual, $this->period('Semester 1'));
        $this->semesters()->addSlot($semester, $item, ['week_label' => 'Minggu 1', 'planned_lesson_periods' => 5]);
        $this->semesters()->addSlot($semester, $item, ['week_label' => 'Minggu 2']); // no JP
        $this->semesters()->activate($semester->fresh());

        try {
            $this->programmes()->updateItem($item->fresh(), null, 8, touchBudget: true);
            $this->fail('a budget was set over slots that do not all state their JP');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('carry no JP', $e->errors()['planned_lesson_periods'][0]);
        }

        $this->assertNull($item->fresh()->planned_lesson_periods);
    }

    /** Documented rule: clearing a budget is always allowed. */
    public function test_clearing_the_annual_budget_is_allowed_and_relaxes_reconciliation(): void
    {
        $this->seedReferenceData();
        [$item, $semester] = $this->activePlanOfEight();

        $this->programmes()->updateItem($item, null, null, touchBudget: true);

        $this->assertNull($item->fresh()->planned_lesson_periods);
        // The slots keep their own figures; nothing was rewritten on their behalf.
        $this->assertSame([2, 2, 4], array_values($this->distribution($semester->fresh())));
        $this->semesters()->assertPlanIsComplete($semester->fresh());

        // And a single slot may now be edited freely, since nothing reconciles.
        $this->semesters()->updateSlot($semester->items()->first(), ['planned_lesson_periods' => 1]);
        $this->assertSame(1, $semester->items()->first()->fresh()->planned_lesson_periods);
    }

    public function test_the_budget_may_be_changed_freely_while_the_schedule_is_still_draft(): void
    {
        $this->seedReferenceData();
        $annual = $this->activatedClassProgramme();
        $item = $annual->items()->first();
        $semester = $this->semesters()->create($annual, $this->period('Semester 1'));
        $this->semesters()->addSlot($semester, $item, ['planned_lesson_periods' => 8]);

        $this->programmes()->updateItem($item, null, 10, touchBudget: true);

        $this->assertSame(10, $item->fresh()->planned_lesson_periods);
    }

    // ------------------------------------------------ active slot edits

    public function test_editing_one_slot_jp_out_of_balance_is_refused(): void
    {
        $this->seedReferenceData();
        [$item, $semester] = $this->activePlanOfEight();
        $last = $semester->items()->get()->last();

        try {
            $this->semesters()->updateSlot($last, ['planned_lesson_periods' => 3]);
            $this->fail('an active schedule was left totalling 7 against an 8 JP budget');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('add up to 7 JP', $e->errors()['items'][0]);
        }

        $this->assertSame([2, 2, 4], array_values($this->distribution($semester->fresh())));
    }

    public function test_adding_a_slot_that_pushes_the_total_past_the_budget_is_refused(): void
    {
        $this->seedReferenceData();
        [$item, $semester] = $this->activePlanOfEight();

        try {
            $this->semesters()->addSlot($semester, $item, ['week_label' => 'Minggu 9', 'planned_lesson_periods' => 1]);
            $this->fail('an active schedule was left totalling 9 against an 8 JP budget');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('add up to 9 JP', $e->errors()['items'][0]);
        }

        $this->assertSame(3, $semester->fresh()->items()->count());
        $this->assertSame([2, 2, 4], array_values($this->distribution($semester->fresh())));
    }

    public function test_adding_a_slot_without_jp_to_a_budgeted_item_is_refused(): void
    {
        $this->seedReferenceData();
        [$item, $semester] = $this->activePlanOfEight();

        $this->expectException(ValidationException::class);

        try {
            $this->semesters()->addSlot($semester, $item, ['week_label' => 'Minggu 9']);
        } finally {
            $this->assertSame(3, $semester->fresh()->items()->count());
        }
    }

    public function test_removing_the_only_slot_of_a_required_objective_is_refused(): void
    {
        $this->seedReferenceData();
        $annual = $this->classProgramme();
        $one = $this->programmes()->addItem($annual, $this->pathwayItem(1), $this->period('Semester 1'), 4);
        $two = $this->programmes()->addItem($annual, $this->pathwayItem(2), $this->period('Semester 1'), 6);
        $annual = $this->programmes()->activate($annual->fresh());

        $semester = $this->semesters()->create($annual, $this->period('Semester 1'));
        $this->semesters()->addSlot($semester, $one, ['planned_lesson_periods' => 4]);
        $twosSlot = $this->semesters()->addSlot($semester, $two, ['planned_lesson_periods' => 6]);
        $this->semesters()->activate($semester->fresh());

        try {
            $this->semesters()->removeSlot($twosSlot);
            $this->fail('the only slot of an allocated objective was removed from an active plan');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('no schedule slot yet', $e->errors()['items'][0]);
        }

        $this->assertSame(2, $semester->fresh()->items()->count());
    }

    public function test_removing_one_of_several_slots_is_refused_when_it_unbalances_the_budget(): void
    {
        $this->seedReferenceData();
        [$item, $semester] = $this->activePlanOfEight();

        $this->expectException(ValidationException::class);

        try {
            $this->semesters()->removeSlot($semester->items()->first());
        } finally {
            $this->assertSame(3, $semester->fresh()->items()->count());
        }
    }

    public function test_week_labels_dates_notes_and_order_may_still_be_edited_on_an_active_plan(): void
    {
        $this->seedReferenceData();
        [$item, $semester] = $this->activePlanOfEight();
        $first = $semester->items()->first();

        $this->semesters()->updateSlot($first, [
            'week_label' => 'Minggu Efektif 3',
            'planned_start_date' => '2026-08-03',
            'planned_end_date' => '2026-08-07',
            'planned_lesson_periods' => $first->planned_lesson_periods,
            'notes' => 'Moved after the holiday.',
        ]);

        $first->refresh();
        $this->assertSame('Minggu Efektif 3', $first->week_label);
        $this->assertSame('2026-08-03', $first->planned_start_date->toDateString());
        $this->assertSame('Moved after the holiday.', $first->notes);

        $this->semesters()->moveSlot($first, 'down');
        $this->assertSame(2, $first->fresh()->position);

        $this->semesters()->assertPlanIsComplete($semester->fresh());
    }

    public function test_a_date_outside_the_period_is_still_refused_on_an_active_plan(): void
    {
        $this->seedReferenceData();
        [$item, $semester] = $this->activePlanOfEight();
        $first = $semester->items()->first();

        $this->expectException(ValidationException::class);

        $this->semesters()->updateSlot($first, [
            'planned_start_date' => '2027-03-01', // Semester 2
            'planned_lesson_periods' => $first->planned_lesson_periods,
        ]);
    }

    // ------------------------------------------------------- rebalancing

    public function test_an_atomic_rebalance_moves_two_two_four_to_three_one_four(): void
    {
        $this->seedReferenceData();
        [$item, $semester] = $this->activePlanOfEight();

        $slots = $semester->items()->get()->values();

        $this->semesters()->rebalance($item, [
            $slots[0]->id => 3,
            $slots[1]->id => 1,
            $slots[2]->id => 4,
        ]);

        $this->assertSame([3, 1, 4], array_values($this->distribution($semester->fresh())));
        $this->semesters()->assertPlanIsComplete($semester->fresh());
    }

    public function test_a_rebalance_that_does_not_add_up_is_refused_whole(): void
    {
        $this->seedReferenceData();
        [$item, $semester] = $this->activePlanOfEight();

        $slots = $semester->items()->get()->values();

        try {
            $this->semesters()->rebalance($item, [
                $slots[0]->id => 3,
                $slots[1]->id => 1,
                $slots[2]->id => 3,
            ]);
            $this->fail('a rebalance totalling 7 was accepted against an 8 JP budget');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('add up to 7 JP', $e->errors()['items'][0]);
        }

        // Nothing partially applied: the whole map rolled back.
        $this->assertSame([2, 2, 4], array_values($this->distribution($semester->fresh())));
    }

    public function test_a_partial_rebalance_map_is_refused(): void
    {
        $this->seedReferenceData();
        [$item, $semester] = $this->activePlanOfEight();
        $slots = $semester->items()->get()->values();

        try {
            $this->semesters()->rebalance($item, [$slots[0]->id => 8]);
            $this->fail('a partial distribution was accepted');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('every slot', $e->errors()['items'][0]);
        }

        $this->assertSame([2, 2, 4], array_values($this->distribution($semester->fresh())));
    }

    public function test_a_rebalance_naming_a_foreign_slot_is_refused(): void
    {
        $this->seedReferenceData();
        [$item, $semester] = $this->activePlanOfEight();
        $slots = $semester->items()->get()->values();

        $this->expectException(ValidationException::class);

        $this->semesters()->rebalance($item, [
            $slots[0]->id => 2, $slots[1]->id => 2, $slots[2]->id => 4, 987654 => 1,
        ]);
    }

    /**
     * Raising a budget is impossible from either side alone -- change the slots
     * and they disagree with 8, change the budget and it disagrees with the
     * slots -- so both move in one operation.
     */
    public function test_budget_and_slots_move_together_from_eight_to_ten(): void
    {
        $this->seedReferenceData();
        [$item, $semester] = $this->activePlanOfEight();
        $slots = $semester->items()->get()->values();

        $this->semesters()->rebalance($item, [
            $slots[0]->id => 3, $slots[1]->id => 3, $slots[2]->id => 4,
        ], budget: 10, touchBudget: true);

        $this->assertSame(10, $item->fresh()->planned_lesson_periods);
        $this->assertSame([3, 3, 4], array_values($this->distribution($semester->fresh())));
        $this->semesters()->assertPlanIsComplete($semester->fresh());
    }

    public function test_a_combined_change_that_does_not_reconcile_is_refused_whole(): void
    {
        $this->seedReferenceData();
        [$item, $semester] = $this->activePlanOfEight();
        $slots = $semester->items()->get()->values();

        try {
            $this->semesters()->rebalance($item, [
                $slots[0]->id => 3, $slots[1]->id => 3, $slots[2]->id => 4,
            ], budget: 12, touchBudget: true);
            $this->fail('a 10 JP distribution was accepted against a 12 JP budget');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('add up to 10 JP', $e->errors()['items'][0]);
        }

        // Neither side moved.
        $this->assertSame(8, $item->fresh()->planned_lesson_periods);
        $this->assertSame([2, 2, 4], array_values($this->distribution($semester->fresh())));
    }

    public function test_the_budget_may_also_be_cleared_through_the_same_operation(): void
    {
        $this->seedReferenceData();
        [$item, $semester] = $this->activePlanOfEight();
        $slots = $semester->items()->get()->values();

        $this->semesters()->rebalance($item, [
            $slots[0]->id => 2, $slots[1]->id => 2, $slots[2]->id => 4,
        ], budget: null, touchBudget: true);

        $this->assertNull($item->fresh()->planned_lesson_periods);
        $this->semesters()->assertPlanIsComplete($semester->fresh());
    }

    // ------------------------------------------------------------ draft

    public function test_a_draft_schedule_may_stay_incomplete_while_it_is_prepared(): void
    {
        $this->seedReferenceData();
        $annual = $this->classProgramme();
        $one = $this->programmes()->addItem($annual, $this->pathwayItem(1), $this->period('Semester 1'), 4);
        $this->programmes()->addItem($annual, $this->pathwayItem(2), $this->period('Semester 1'), 6);
        $annual = $this->programmes()->activate($annual->fresh());

        $semester = $this->semesters()->create($annual, $this->period('Semester 1'));
        $slot = $this->semesters()->addSlot($semester, $one, ['planned_lesson_periods' => 1]);

        // Incomplete (nothing for the second objective) and unreconciled
        // (1 against 4) -- and all of that is fine while it is a draft.
        $this->assertTrue($semester->fresh()->isDraft());
        $this->assertSame(1, $semester->fresh()->items()->count());

        $this->semesters()->updateSlot($slot, ['planned_lesson_periods' => 2]);
        $this->assertSame(2, $slot->fresh()->planned_lesson_periods);

        // Structural integrity still applies to a draft.
        $this->expectException(ValidationException::class);
        $this->semesters()->updateSlot($slot, ['planned_start_date' => '2027-03-01']);
    }

    public function test_activation_is_still_the_gate_for_a_draft(): void
    {
        $this->seedReferenceData();
        $annual = $this->activatedClassProgramme();
        $semester = $this->semesters()->create($annual, $this->period('Semester 1'));
        $this->semesters()->addSlot($semester, $annual->items()->first(), ['planned_lesson_periods' => 3]);

        $this->expectException(ValidationException::class);

        $this->semesters()->activate($semester->fresh());
    }

    // -------------------------------------------------------- archiving

    public function test_an_annual_programme_cannot_be_archived_while_a_child_schedule_is_active(): void
    {
        $this->seedReferenceData();
        [$item, $semester] = $this->activePlanOfEight();
        $annual = $item->annualProgramme;

        try {
            $this->programmes()->archive($annual);
            $this->fail('the annual programme was archived over a live semester plan');
        } catch (ValidationException $e) {
            $message = $e->errors()['status'][0];
            $this->assertStringContainsString('Semester 1', $message);
            $this->assertStringContainsString('first', $message);
        }

        $this->assertTrue($annual->fresh()->isActive());
        $this->assertTrue($semester->fresh()->isActive());
    }

    public function test_archiving_bottom_up_succeeds(): void
    {
        $this->seedReferenceData();
        [$item, $semester] = $this->activePlanOfEight();
        $annual = $item->annualProgramme;

        $this->semesters()->archive($semester);
        $this->programmes()->archive($annual->fresh());

        $this->assertTrue($annual->fresh()->isArchived());
        $this->assertTrue($semester->fresh()->isArchived());
    }

    public function test_a_draft_child_schedule_does_not_block_archiving(): void
    {
        $this->seedReferenceData();
        $annual = $this->activatedClassProgramme();
        $this->semesters()->create($annual, $this->period('Semester 1'));

        $this->programmes()->archive($annual);

        $this->assertTrue($annual->fresh()->isArchived());
    }

    public function test_archiving_the_annual_programme_never_touches_its_schedules(): void
    {
        $this->seedReferenceData();
        [$item, $semester] = $this->activePlanOfEight();

        $before = $semester->items()->get()->map->only(['id', 'position', 'week_label', 'planned_lesson_periods']);

        $this->semesters()->archive($semester);
        $this->programmes()->archive($item->annualProgramme->fresh());

        $after = $semester->fresh()->items()->get()->map->only(['id', 'position', 'week_label', 'planned_lesson_periods']);

        $this->assertEquals($before->all(), $after->all());
    }

    public function test_an_archived_schedule_is_readable_and_immutable(): void
    {
        $this->seedReferenceData();
        [$item, $semester] = $this->activePlanOfEight();

        $this->semesters()->archive($semester);
        $archived = $semester->fresh();

        // Readable.
        $this->assertSame(3, $archived->items()->count());
        $this->assertSame([2, 2, 4], array_values($this->distribution($archived)));

        // Immutable through every slot path.
        foreach ([
            fn () => $this->semesters()->addSlot($archived, $item, ['planned_lesson_periods' => 1]),
            fn () => $this->semesters()->updateSlot($archived->items()->first(), ['planned_lesson_periods' => 1]),
            fn () => $this->semesters()->removeSlot($archived->items()->first()),
            fn () => $this->semesters()->moveSlot($archived->items()->first(), 'down'),
            fn () => $this->semesters()->rebalance($item->fresh(), $this->distribution($archived)),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('an archived semester programme accepted a write');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('read-only', $e->errors()['status'][0]);
            }
        }

        $this->assertSame([2, 2, 4], array_values($this->distribution($semester->fresh())));
    }

    public function test_the_model_itself_refuses_to_reopen_an_archived_schedule(): void
    {
        $this->seedReferenceData();
        [, $semester] = $this->activePlanOfEight();
        $this->semesters()->archive($semester);

        $this->expectException(LogicException::class);

        $semester->fresh()->update(['status' => 'active']);
    }

    // ------------------------------------------------------------ audit

    public function test_a_refused_edit_leaves_no_audit_trail(): void
    {
        $this->seedReferenceData();
        [$item, $semester] = $this->activePlanOfEight();

        $before = $this->auditCount(SemesterProgrammeItem::class, 'updated');

        try {
            $this->semesters()->updateSlot($semester->items()->first(), ['planned_lesson_periods' => 1]);
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame($before, $this->auditCount(SemesterProgrammeItem::class, 'updated'));
    }

    public function test_a_successful_rebalance_is_audited_once_per_changed_slot(): void
    {
        $this->seedReferenceData();
        [$item, $semester] = $this->activePlanOfEight();
        $slots = $semester->items()->get()->values();

        $before = $this->auditCount(SemesterProgrammeItem::class, 'updated');

        // 2 -> 3 and 2 -> 1 change; 4 -> 4 does not.
        $this->semesters()->rebalance($item, [
            $slots[0]->id => 3, $slots[1]->id => 1, $slots[2]->id => 4,
        ]);

        $this->assertSame($before + 2, $this->auditCount(SemesterProgrammeItem::class, 'updated'));
    }
}
