<?php

namespace Tests\Feature;

use App\Models\AnnualProgramme;
use App\Models\AnnualProgrammeItem;
use App\Models\AuditLog;
use App\Models\SemesterProgramme;
use App\Models\SemesterProgrammeItem;
use App\Services\SemesterProgrammeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsPlanningFixtures;
use Tests\TestCase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Phase 5E: Program Semester.
 *
 * The annual programme says WHICH PERIOD; this says WHEN INSIDE IT. One
 * allocation may need several slots -- an 8 JP objective taught across three
 * weeks is three rows sharing one budget -- which is why there is deliberately
 * no uniqueness rule on (programme, annual item).
 *
 * Shares the Prota suite's fixture graph through a trait rather than by
 * inheritance, so neither suite re-runs the other's tests.
 */
class SemesterProgrammeTest extends TestCase
{
    use BuildsPlanningFixtures, RefreshDatabase;

    // ---------------------------------------------------------- multi-slot

    public function test_one_annual_item_may_be_scheduled_across_several_slots(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->planWithBudget(8);
        $item = $annual->items()->first();

        foreach ([['Week 3', 2], ['Week 4', 2], ['Minggu Efektif 7', 4]] as [$label, $jp]) {
            $this->semesters()->addSlot($semester, $item, ['week_label' => $label, 'planned_lesson_periods' => $jp]);
        }

        $slots = $semester->fresh()->items()->get();

        $this->assertSame(3, $slots->count(), 'one objective, three teaching slots');
        $this->assertSame(['Week 3', 'Week 4', 'Minggu Efektif 7'], $slots->pluck('week_label')->all());
        $this->assertSame([1, 2, 3], $slots->pluck('position')->all());
        $this->assertSame(8, (int) $slots->sum('planned_lesson_periods'));
    }

    public function test_there_is_no_unique_constraint_forcing_one_slot_per_item(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->planWithBudget(null);
        $item = $annual->items()->first();

        // Two raw rows for the same annual item must be accepted.
        foreach ([1, 2] as $position) {
            SemesterProgrammeItem::create([
                'semester_programme_id' => $semester->id,
                'annual_programme_item_id' => $item->id,
                'annual_programme_id' => $semester->annual_programme_id,
                'academic_period_id' => $semester->academic_period_id,
                'position' => $position,
            ]);
        }

        $this->assertSame(2, $semester->fresh()->items()->count());
    }

    public function test_a_slot_carries_no_pathway_item_column(): void
    {
        $this->assertFalse(
            Schema::hasColumn('semester_programme_items', 'learning_pathway_item_id'),
            'already determined by the annual item; a second path could disagree'
        );
    }

    public function test_week_labels_accept_flexible_text(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->planWithBudget(null);

        foreach (['Week 3', 'Weeks 4-5', 'Minggu Efektif 7', 'After Mid-Semester Assessment'] as $label) {
            $slot = $this->semesters()->addSlot($semester, $annual->items()->first(), ['week_label' => $label]);
            $this->assertSame($label, $slot->week_label);
        }
    }

    // ----------------------------------------------------- context integrity

    public function test_a_semester_two_allocation_cannot_be_scheduled_in_semester_one(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->planWithBudget(null);

        $secondTerm = $this->programmes()->addItem($annual, $this->pathwayItem(2), $this->period('Semester 2'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('not allocated to this period');

        $this->semesters()->addSlot($semester, $secondTerm, ['week_label' => 'Week 1']);
    }

    public function test_the_database_also_refuses_a_cross_period_slot(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->planWithBudget(null);
        $secondTerm = $this->programmes()->addItem($annual, $this->pathwayItem(2), $this->period('Semester 2'));

        $this->expectException(QueryException::class);
        SemesterProgrammeItem::create([
            'semester_programme_id' => $semester->id,
            'annual_programme_item_id' => $secondTerm->id,
            'annual_programme_id' => $semester->annual_programme_id,
            'academic_period_id' => $semester->academic_period_id,
            'position' => 1,
        ]);
    }

    public function test_only_one_semester_programme_per_period(): void
    {
        $this->seedReferenceData();
        [$annual] = $this->planWithBudget(null);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('already has a Semester 1 programme');

        $this->semesters()->create($annual, $this->period('Semester 1'));
    }

    public function test_a_period_from_another_year_is_refused(): void
    {
        $this->seedReferenceData();
        $annual = $this->activatedClassProgramme();

        $other = \App\Models\AcademicYear::create(['name' => '2027/2028', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'is_current' => false]);
        $foreign = \App\Models\AcademicPeriod::create(['academic_year_id' => $other->id, 'name' => 'Next S1', 'sequence' => 1, 'start_date' => '2027-07-01', 'end_date' => '2027-12-31']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('different academic year');

        $this->semesters()->create($annual, $foreign);
    }

    // --------------------------------------------------------------- dates

    public function test_dates_must_fall_inside_the_period(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->planWithBudget(null);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('must fall inside Semester 1');

        $this->semesters()->addSlot($semester, $annual->items()->first(), [
            'planned_start_date' => '2027-03-01',   // Semester 2 territory
        ]);
    }

    public function test_an_end_date_before_the_start_is_refused(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->planWithBudget(null);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cannot be before the start date');

        $this->semesters()->addSlot($semester, $annual->items()->first(), [
            'planned_start_date' => '2026-10-10', 'planned_end_date' => '2026-10-01',
        ]);
    }

    public function test_an_end_date_without_a_start_is_refused(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->planWithBudget(null);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('needs a start date');

        $this->semesters()->addSlot($semester, $annual->items()->first(), ['planned_end_date' => '2026-10-01']);
    }

    public function test_valid_dates_inside_the_period_are_accepted(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->planWithBudget(null);

        $slot = $this->semesters()->addSlot($semester, $annual->items()->first(), [
            'planned_start_date' => '2026-09-01', 'planned_end_date' => '2026-09-12',
        ]);

        $this->assertSame('2026-09-01', $slot->planned_start_date->toDateString());
        $this->assertSame('2026-09-12', $slot->planned_end_date->toDateString());
    }

    // --------------------------------------------------- position handling

    public function test_positions_stay_contiguous_after_removal_and_reorder(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->planWithBudget(null);
        $item = $annual->items()->first();

        $slots = collect([1, 2, 3, 4])->map(fn ($n) => $this->semesters()->addSlot($semester, $item, ['week_label' => "Week {$n}"]));

        $this->semesters()->removeSlot($slots[1]);
        $this->assertSame([1, 2, 3], $semester->fresh()->items()->pluck('position')->all());

        $ordered = $semester->fresh()->items()->get();
        $this->semesters()->moveSlot($ordered->last(), 'up');

        $after = $semester->fresh()->items()->get();
        $this->assertSame([1, 2, 3], $after->pluck('position')->all());
        $this->assertSame(['Week 1', 'Week 4', 'Week 3'], $after->pluck('week_label')->all());
    }

    public function test_normalisation_repairs_a_gapped_schedule(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->planWithBudget(null);
        $item = $annual->items()->first();

        $slots = collect([1, 2, 3])->map(fn ($n) => $this->semesters()->addSlot($semester, $item, ['week_label' => "Week {$n}"]));

        // Raw SQL can still gap a schedule -- the documented application-level
        // constraint. Normalisation repairs it.
        \DB::table('semester_programme_items')->where('id', $slots[2]->id)->update(['position' => 9]);
        \DB::table('semester_programme_items')->where('id', $slots[1]->id)->update(['position' => 1]);

        $this->semesters()->normalise($semester->fresh());

        $this->assertSame([1, 2, 3], $semester->fresh()->items()->pluck('position')->all());
    }

    // ------------------------------------------------------- JP reconciliation

    public function test_a_budget_of_eight_with_slots_of_two_two_and_four_activates(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->planWithBudget(8);
        $item = $annual->items()->first();

        foreach ([2, 2, 4] as $jp) {
            $this->semesters()->addSlot($semester, $item, ['planned_lesson_periods' => $jp]);
        }

        $this->assertTrue($this->semesters()->activate($semester->fresh())->isActive());
    }

    public function test_a_budget_of_eight_with_slots_summing_to_seven_is_refused(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->planWithBudget(8);
        $item = $annual->items()->first();

        foreach ([2, 2, 3] as $jp) {
            $this->semesters()->addSlot($semester, $item, ['planned_lesson_periods' => $jp]);
        }

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('add up to 7 JP but the annual budget is 8');

        $this->semesters()->activate($semester->fresh());
    }

    public function test_a_budget_with_a_partially_unspecified_slot_is_refused(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->planWithBudget(8);
        $item = $annual->items()->first();

        $this->semesters()->addSlot($semester, $item, ['planned_lesson_periods' => 4]);
        $this->semesters()->addSlot($semester, $item, []);   // no JP

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('every one of its slots needs its own JP');

        $this->semesters()->activate($semester->fresh());
    }

    public function test_no_budget_means_slot_jp_stays_optional(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->planWithBudget(null);

        $this->semesters()->addSlot($semester, $annual->items()->first(), ['week_label' => 'Week 1']);

        $this->assertTrue($this->semesters()->activate($semester->fresh())->isActive(), 'nothing to reconcile against');
    }

    public function test_a_zero_jp_slot_is_refused(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->planWithBudget(null);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('at least 1');

        $this->semesters()->addSlot($semester, $annual->items()->first(), ['planned_lesson_periods' => 0]);
    }

    // ---------------------------------------------------------- completeness

    public function test_activation_requires_every_allocated_item_to_be_scheduled(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->planWithBudget(null);

        // A second item allocated to the SAME period, deliberately unscheduled.
        $this->programmes()->addItem($annual, $this->pathwayItem(2), $this->period('Semester 1'));
        $this->semesters()->addSlot($semester, $annual->items()->first(), ['week_label' => 'Week 1']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('has no schedule slot yet');

        $this->semesters()->activate($semester->fresh());
    }

    public function test_items_allocated_to_another_period_are_not_required(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->planWithBudget(null);

        // Allocated to Semester 2 -- irrelevant to the Semester 1 schedule.
        $this->programmes()->addItem($annual, $this->pathwayItem(2), $this->period('Semester 2'));
        $this->semesters()->addSlot($semester, $annual->items()->where('academic_period_id', $this->period('Semester 1')->id)->first(), ['week_label' => 'Week 1']);

        $this->assertTrue($this->semesters()->activate($semester->fresh())->isActive());
    }

    public function test_activation_requires_an_active_annual_programme(): void
    {
        $this->seedReferenceData();
        $annual = $this->classProgramme();
        $this->programmes()->addItem($annual, $this->pathwayItem(1), $this->period('Semester 1'));
        $semester = $this->semesters()->create($annual, $this->period('Semester 1'));
        $this->semesters()->addSlot($semester, $annual->items()->first(), ['week_label' => 'Week 1']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The annual programme is draft');

        $this->semesters()->activate($semester->fresh());
    }

    public function test_activation_is_atomic_when_validation_fails(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->planWithBudget(8);
        $this->semesters()->addSlot($semester, $annual->items()->first(), ['planned_lesson_periods' => 3]);

        try {
            $this->semesters()->activate($semester->fresh());
            $this->fail('should have been refused');
        } catch (ValidationException) {
        }

        $this->assertTrue($semester->fresh()->isDraft());
    }

    // ------------------------------------------------------------- lifecycle

    public function test_an_active_schedule_stays_editable_and_is_audited(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->planWithBudget(null);
        $slot = $this->semesters()->addSlot($semester, $annual->items()->first(), ['week_label' => 'Week 1']);
        $this->semesters()->activate($semester->fresh());

        $before = $this->auditCount(SemesterProgrammeItem::class, 'updated');
        $this->semesters()->updateSlot($slot->fresh(), ['week_label' => 'Week 2']);

        $this->assertSame('Week 2', $slot->fresh()->week_label, 'a live schedule can still shift');
        $this->assertSame($before + 1, $this->auditCount(SemesterProgrammeItem::class, 'updated'));
    }

    public function test_an_archived_schedule_is_read_only(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->planWithBudget(null);
        $this->semesters()->addSlot($semester, $annual->items()->first(), ['week_label' => 'Week 1']);
        $active = $this->semesters()->activate($semester->fresh());
        $archived = $this->semesters()->archive($active);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('read-only');

        $this->semesters()->addSlot($archived, $annual->items()->first(), ['week_label' => 'Week 9']);
    }

    public function test_an_archived_annual_programme_blocks_new_semester_planning(): void
    {
        $this->seedReferenceData();
        $annual = $this->activatedClassProgramme();
        $this->programmes()->archive($annual);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('archived annual programme');

        $this->semesters()->create($annual->fresh(), $this->period('Semester 1'));
    }

    public function test_a_draft_schedule_can_be_deleted_with_its_slots(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->planWithBudget(null);
        $this->semesters()->addSlot($semester, $annual->items()->first(), ['week_label' => 'Week 1']);

        $before = $this->auditCount(SemesterProgrammeItem::class, 'deleted');
        $this->semesters()->delete($semester->fresh());

        $this->assertSame(0, SemesterProgramme::count());
        $this->assertSame(0, SemesterProgrammeItem::count());
        $this->assertSame($before + 1, $this->auditCount(SemesterProgrammeItem::class, 'deleted'));
    }

    // -------------------------------------------------------- authorization

    public function test_semester_planning_follows_the_annual_programmes_authority(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->planWithBudget(null);

        $sarah = $this->teacherFor('Year 5A', 'Maths', 'Sarah');
        $this->assertTrue($sarah->can('update', $semester));
        $this->assertFalse($sarah->can('transition', $semester), 'activation stays with management');

        \App\Models\ClassSubject::where('staff_id', $sarah->staff->id)->update(['ended_on' => '2026-12-15']);
        $eka = $this->teacherFor('Year 5A', 'Maths', 'Eka', '2026-12-16');

        $this->assertFalse($sarah->fresh()->can('update', $semester));
        $this->assertTrue($eka->can('update', $semester), 'the successor continues the same schedule');
        $this->assertTrue($this->userWithRole('principal')->can('transition', $semester));
    }

    // ------------------------------------------------------------- audit

    public function test_schedule_changes_are_audited(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->planWithBudget(null);

        $creates = $this->auditCount(SemesterProgrammeItem::class, 'created');
        $first = $this->semesters()->addSlot($semester, $annual->items()->first(), ['week_label' => 'Week 1']);
        $second = $this->semesters()->addSlot($semester, $annual->items()->first(), ['week_label' => 'Week 2']);
        $this->assertSame($creates + 2, $this->auditCount(SemesterProgrammeItem::class, 'created'));

        $updates = $this->auditCount(SemesterProgrammeItem::class, 'updated');
        $this->semesters()->moveSlot($second->fresh(), 'up');
        $this->assertGreaterThan($updates, $this->auditCount(SemesterProgrammeItem::class, 'updated'));

        $programmeUpdates = $this->auditCount(SemesterProgramme::class, 'updated');
        $this->semesters()->activate($semester->fresh());
        $this->assertSame($programmeUpdates + 1, $this->auditCount(SemesterProgramme::class, 'updated'));
    }

    // -------------------------------------------------------- delete safety

    public function test_no_module_or_journal_tables_exist_yet(): void
    {
        foreach (['teaching_modules', 'modul_ajar', 'daily_journals', 'teaching_journals', 'jurnal_harian'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "{$table} belongs to a later phase");
        }
    }

    // --------------------------------------------------------------- helpers

    /** @return array{0: AnnualProgramme, 1: SemesterProgramme} */
    protected function planWithBudget(?int $budget): array
    {
        $annual = $this->classProgramme();
        $this->programmes()->addItem($annual, $this->pathwayItem(1), $this->period('Semester 1'), $budget);
        $annual = $this->programmes()->activate($annual->fresh());

        $semester = $this->semesters()->create($annual, $this->period('Semester 1'));

        return [$annual->fresh(), $semester];
    }

    protected function semesters(): SemesterProgrammeService
    {
        return app(SemesterProgrammeService::class);
    }
}
