<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\AnnualProgramme;
use App\Models\AnnualProgrammeItem;
use App\Models\AuditLog;
use App\Models\ClassSubject;
use App\Models\Curriculum;
use App\Models\CurriculumScope;
use App\Models\EnglishLevel;
use App\Models\Grade;
use App\Models\LearningObjective;
use App\Models\LearningPathway;
use App\Models\LearningPathwayItem;
use App\Models\LearningPhase;
use App\Models\Position;
use App\Models\SchoolClass;
use App\Models\SemesterProgramme;
use App\Models\SemesterProgrammeItem;
use App\Models\Staff;
use App\Models\Subject;
use App\Models\TeachingGroup;
use App\Models\User;
use App\Services\AnnualProgrammeService;
use App\Services\CurriculumScopeService;
use App\Services\SemesterProgrammeService;
use Database\Seeders\EnglishProgrammeSeeder;
use Database\Seeders\GradeSeeder;
use Database\Seeders\LearningPhaseSeeder;
use Database\Seeders\PositionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsPlanningFixtures;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 5E: Program Tahunan and Program Semester.
 *
 * Prota allocates pathway items to reporting periods for a real roster; Prosem
 * schedules those allocations into slots inside one period. The load-bearing
 * decision is that Prota is anchored to the ROSTER, so a teacher handover
 * leaves the plan intact.
 */
class AnnualProgrammeTest extends TestCase
{
    use BuildsPlanningFixtures, RefreshDatabase;

    // ------------------------------------------------------------- anchoring

    public function test_an_annual_programme_is_anchored_to_the_roster_not_the_assignment(): void
    {
        $this->seedReferenceData();
        $programme = $this->classProgramme();

        $this->assertSame($this->class('Year 5', 'Year 5A')->id, $programme->class_id);
        $this->assertNull($programme->teaching_group_id);
        $this->assertSame($this->year->id, $programme->academic_year_id);

        $this->assertFalse(Schema::hasColumn('annual_programmes', 'class_subject_id'));
        $this->assertFalse(Schema::hasColumn('annual_programmes', 'staff_id'));
        $this->assertFalse(Schema::hasColumn('annual_programmes', 'owner_staff_id'));
        $this->assertFalse(Schema::hasColumn('annual_programmes', 'grade_id'));
    }

    public function test_identity_fields_are_immutable(): void
    {
        $this->seedReferenceData();
        $programme = $this->classProgramme();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('fixed at creation');

        $programme->update(['subject_id' => $this->subject('Eng')->id]);
    }

    public function test_the_database_refuses_both_or_neither_roster_source(): void
    {
        $this->seedReferenceData();
        $pathway = $this->pathway();

        foreach ([['class_id' => $this->class('Year 5', 'Year 5A')->id, 'teaching_group_id' => $this->group('Green')->id], ['class_id' => null, 'teaching_group_id' => null]] as $roster) {
            try {
                AnnualProgramme::create($roster + [
                    'subject_id' => $pathway->subject_id,
                    'academic_year_id' => $this->year->id,
                    'curriculum_scope_id' => $pathway->curriculum_scope_id,
                    'learning_pathway_id' => $pathway->id,
                    'status' => 'draft',
                ]);
                $this->fail('the CHECK constraint should have refused this');
            } catch (QueryException) {
                // expected
            }
        }

        $this->assertSame(0, AnnualProgramme::count());
    }

    public function test_the_database_refuses_a_year_that_is_not_the_rosters(): void
    {
        $this->seedReferenceData();
        $other = AcademicYear::create(['name' => '2027/2028', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'is_current' => false]);
        $pathway = $this->pathway();

        $this->expectException(QueryException::class);
        AnnualProgramme::create([
            'class_id' => $this->class('Year 5', 'Year 5A')->id,
            'subject_id' => $pathway->subject_id,
            'academic_year_id' => $other->id,
            'curriculum_scope_id' => $pathway->curriculum_scope_id,
            'learning_pathway_id' => $pathway->id,
            'status' => 'draft',
        ]);
    }

    public function test_the_database_refuses_a_scope_that_is_not_the_pathways(): void
    {
        $this->seedReferenceData();
        $pathway = $this->pathway();
        $phaseD = $this->scopeFor('D');

        $this->expectException(QueryException::class);
        AnnualProgramme::create([
            'class_id' => $this->class('Year 5', 'Year 5A')->id,
            'subject_id' => $pathway->subject_id,
            'academic_year_id' => $this->year->id,
            'curriculum_scope_id' => $phaseD->id,
            'learning_pathway_id' => $pathway->id,
            'status' => 'draft',
        ]);
    }

    // ------------------------------------------------------ eligibility

    public function test_a_class_may_follow_a_pathway_for_its_own_phase(): void
    {
        $this->seedReferenceData();
        $this->assertTrue($this->classProgramme()->isDraft());
    }

    public function test_a_class_cannot_follow_a_pathway_for_another_phase(): void
    {
        $this->seedReferenceData();
        $phaseD = $this->pathwayFor('D', 'Maths', 'Phase D route');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('covers Phase D');

        $this->programmes()->createForClass($this->class('Year 5', 'Year 5A'), $this->subject('Maths'), $phaseD);
    }

    public function test_a_class_cannot_follow_an_english_level_pathway(): void
    {
        $this->seedReferenceData();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cannot be followed by a class');

        $this->programmes()->createForClass($this->class('Year 5', 'Year 5A'), $this->subject('Eng'), $this->englishPathway('Green'));
    }

    public function test_a_teaching_group_may_follow_its_own_level_pathway(): void
    {
        $this->seedReferenceData();
        $programme = $this->groupProgramme();

        $this->assertSame($this->group('Green')->id, $programme->teaching_group_id);
        $this->assertNull($programme->class_id);
    }

    public function test_green_a_cannot_follow_the_blue_pathway(): void
    {
        $this->seedReferenceData();
        $blue = $this->englishPathway('Blue');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('covers Blue');

        $this->programmes()->createForGroup($this->group('Green'), $this->subject('Eng'), $blue);
    }

    public function test_a_teaching_group_cannot_follow_a_national_phase_pathway(): void
    {
        $this->seedReferenceData();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cannot be followed by a teaching group');

        $this->programmes()->createForGroup($this->group('Green'), $this->subject('Maths'), $this->pathway());
    }

    public function test_the_subject_must_match_the_pathway(): void
    {
        $this->seedReferenceData();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('pathway, not Eng');

        $this->programmes()->createForClass($this->class('Year 5', 'Year 5A'), $this->subject('Eng'), $this->pathway());
    }

    public function test_the_eligible_pathway_list_only_offers_matching_ones(): void
    {
        $this->seedReferenceData();
        $this->pathway();
        $this->pathwayFor('D', 'Maths', 'Phase D route');

        $eligible = $this->programmes()->eligiblePathways($this->subject('Maths'), $this->class('Year 5', 'Year 5A'), null);

        $this->assertSame(['Phase C route'], $eligible->pluck('title')->all());
    }

    // ---------------------------------------------------- period allocation

    public function test_items_are_allocated_to_a_period_with_an_optional_budget(): void
    {
        $this->seedReferenceData();
        $programme = $this->classProgramme();

        $item = $this->programmes()->addItem($programme, $this->pathwayItem(1), $this->period('Semester 1'), 8);

        $this->assertSame($this->period('Semester 1')->id, $item->academic_period_id);
        $this->assertSame(8, $item->planned_lesson_periods);
    }

    public function test_a_period_from_another_year_is_refused_by_the_database(): void
    {
        $this->seedReferenceData();
        $programme = $this->classProgramme();

        $other = AcademicYear::create(['name' => '2027/2028', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'is_current' => false]);
        $foreign = AcademicPeriod::create(['academic_year_id' => $other->id, 'name' => 'Next S1', 'sequence' => 1, 'start_date' => '2027-07-01', 'end_date' => '2027-12-31']);

        $this->expectException(QueryException::class);
        AnnualProgrammeItem::create([
            'annual_programme_id' => $programme->id,
            'learning_pathway_item_id' => $this->pathwayItem(1)->id,
            'learning_pathway_id' => $programme->learning_pathway_id,
            'academic_year_id' => $programme->academic_year_id,
            'academic_period_id' => $foreign->id,
        ]);
    }

    public function test_an_item_from_another_pathway_is_refused_by_the_database(): void
    {
        $this->seedReferenceData();
        $programme = $this->classProgramme();
        $otherPathway = $this->pathwayFor('D', 'Maths', 'Phase D route');
        $foreignItem = $this->itemOn($otherPathway, $this->objectiveIn($this->scopeFor('D'), 'Maths', 'Phase D TP', 1), 1);

        $this->expectException(QueryException::class);
        AnnualProgrammeItem::create([
            'annual_programme_id' => $programme->id,
            'learning_pathway_item_id' => $foreignItem->id,
            'learning_pathway_id' => $programme->learning_pathway_id,
            'academic_year_id' => $programme->academic_year_id,
            'academic_period_id' => $this->period('Semester 1')->id,
        ]);
    }

    public function test_a_pathway_item_may_be_allocated_only_once(): void
    {
        $this->seedReferenceData();
        $programme = $this->classProgramme();
        $this->programmes()->addItem($programme, $this->pathwayItem(1), $this->period('Semester 1'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('already allocated');

        $this->programmes()->addItem($programme, $this->pathwayItem(1), $this->period('Semester 2'));
    }

    public function test_a_zero_lesson_period_budget_is_refused(): void
    {
        $this->seedReferenceData();
        $programme = $this->classProgramme();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('at least 1');

        $this->programmes()->addItem($programme, $this->pathwayItem(1), $this->period('Semester 1'), 0);
    }

    // ------------------------------------------------- multi-grade sharing

    public function test_year_5_and_year_6_share_one_pathway_with_different_allocations(): void
    {
        $this->seedReferenceData();
        $pathway = $this->pathway();

        for ($i = 1; $i <= 6; $i++) {
            $this->itemOn($pathway, $this->objectiveIn($this->scopeFor('C'), 'Maths', "TP {$i}", $i), $i);
        }

        $year5 = $this->programmes()->createForClass($this->class('Year 5', 'Year 5A'), $this->subject('Maths'), $pathway);
        $year6 = $this->programmes()->createForClass($this->class('Year 6', 'Year 6A'), $this->subject('Maths'), $pathway);

        foreach ([1, 2, 3] as $n) {
            $this->programmes()->addItem($year5, $this->pathwayItem($n), $this->period('Semester 1'));
        }
        foreach ([4, 5, 6] as $n) {
            $this->programmes()->addItem($year6, $this->pathwayItem($n), $this->period('Semester 1'));
        }

        $this->assertSame($year5->learning_pathway_id, $year6->learning_pathway_id, 'one pathway, not two');
        $this->assertSame(1, LearningPathway::count(), 'the ATP is never duplicated');
        $this->assertSame(3, $year5->items()->count());
        $this->assertSame(3, $year6->items()->count());
    }

    // ------------------------------------------------------------ activation

    public function test_activation_requires_an_active_pathway(): void
    {
        $this->seedReferenceData();
        $programme = $this->classProgramme();
        $this->programmes()->addItem($programme, $this->pathwayItem(1), $this->period('Semester 1'));

        $programme->learningPathway->update(['status' => 'draft']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Only an active pathway');

        $this->programmes()->activate($programme->fresh());
    }

    public function test_activation_requires_at_least_one_item(): void
    {
        $this->seedReferenceData();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('at least one objective');

        $this->programmes()->activate($this->classProgramme());
    }

    public function test_a_valid_programme_activates(): void
    {
        $this->seedReferenceData();
        $this->assertTrue($this->activatedClassProgramme()->isActive());
    }

    public function test_a_second_active_programme_for_one_roster_and_subject_is_refused(): void
    {
        $this->seedReferenceData();
        $this->activatedClassProgramme();

        $second = $this->programmes()->createForClass($this->class('Year 5', 'Year 5A'), $this->subject('Maths'), $this->pathway());
        $this->programmes()->addItem($second, $this->pathwayItem(2), $this->period('Semester 1'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('already has an active');

        $this->programmes()->activate($second->fresh());
    }

    public function test_activation_does_not_require_the_whole_pathway(): void
    {
        $this->seedReferenceData();
        $pathway = $this->pathway();
        foreach ([1, 2, 3] as $i) {
            $this->itemOn($pathway, $this->objectiveIn($this->scopeFor('C'), 'Maths', "TP {$i}", $i), $i);
        }

        $programme = $this->programmes()->createForClass($this->class('Year 5', 'Year 5A'), $this->subject('Maths'), $pathway);
        $this->programmes()->addItem($programme, $this->pathwayItem(1), $this->period('Semester 1'));

        $this->assertTrue($this->programmes()->activate($programme->fresh())->isActive(), 'one of three items is enough');
    }

    // -------------------------------------------------- teacher succession

    public function test_a_teacher_handover_leaves_the_same_annual_programme(): void
    {
        $this->seedReferenceData();
        $programme = $this->classProgramme();
        $id = $programme->id;

        $sarah = $this->teacherFor('Year 5A', 'Maths', 'Sarah');
        $this->assertTrue($sarah->can('update', $programme), 'Sarah plans while she holds the assignment');

        // Handover: close Sarah's assignment, open Eka's -- Step 0's pattern.
        ClassSubject::where('staff_id', $sarah->staff->id)->update(['ended_on' => '2026-12-15']);
        $eka = $this->teacherFor('Year 5A', 'Maths', 'Eka', '2026-12-16');

        $programme->refresh();

        $this->assertSame($id, $programme->id, 'the plan does not move');
        $this->assertSame(1, AnnualProgramme::count(), 'and is not duplicated');
        $this->assertFalse($sarah->fresh()->can('update', $programme), 'Sarah loses write');
        $this->assertTrue($sarah->fresh()->can('view', $programme), 'but keeps read');
        $this->assertTrue($eka->can('update', $programme), 'Eka continues the same plan');
    }

    public function test_the_same_succession_rule_applies_to_english_groups(): void
    {
        $this->seedReferenceData();
        $programme = $this->groupProgramme();

        $sarah = $this->groupTeacherFor('Green', 'Eng', 'GSarah');
        $this->assertTrue($sarah->can('update', $programme));

        ClassSubject::where('staff_id', $sarah->staff->id)->update(['ended_on' => '2026-12-15']);
        $eka = $this->groupTeacherFor('Green', 'Eng', 'GEka', '2026-12-16');

        $this->assertFalse($sarah->fresh()->can('update', $programme));
        $this->assertTrue($eka->can('update', $programme));
    }

    public function test_an_unrelated_teacher_cannot_plan(): void
    {
        $this->seedReferenceData();
        $programme = $this->classProgramme();

        $other = $this->userWithRole('teacher');

        $this->assertTrue($other->can('academics.plan'));
        $this->assertFalse($other->can('update', $programme));
    }

    public function test_a_teacher_cannot_activate_or_archive(): void
    {
        $this->seedReferenceData();
        $programme = $this->classProgramme();
        $sarah = $this->teacherFor('Year 5A', 'Maths', 'Sarah');

        $this->assertTrue($sarah->can('update', $programme));
        $this->assertFalse($sarah->can('transition', $programme));
        $this->assertTrue($this->userWithRole('principal')->can('transition', $programme));
    }

    // ------------------------------------------------------------ lifecycle

    public function test_an_active_programme_stays_editable(): void
    {
        $this->seedReferenceData();
        $programme = $this->activatedClassProgramme();

        $item = $this->programmes()->addItem($programme, $this->pathwayItem(2), $this->period('Semester 2'), 4);

        $this->assertSame(2, $programme->fresh()->items()->count(), 'a live plan can still be adjusted');
        $this->assertTrue($this->userWithRole('principal')->can('update', $programme));
    }

    public function test_active_allocation_changes_are_audited(): void
    {
        $this->seedReferenceData();
        $programme = $this->activatedClassProgramme();
        $item = $programme->items()->first();

        $before = $this->auditCount(AnnualProgrammeItem::class, 'updated');
        $this->programmes()->updateItem($item, $this->period('Semester 2'));

        $this->assertSame($before + 1, $this->auditCount(AnnualProgrammeItem::class, 'updated'));
        $this->assertSame($this->period('Semester 2')->id, $item->fresh()->academic_period_id);
    }

    public function test_an_archived_programme_is_read_only(): void
    {
        $this->seedReferenceData();
        $programme = $this->programmes()->archive($this->activatedClassProgramme());

        $this->assertFalse($this->userWithRole('principal')->can('update', $programme));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('read-only');
        $programme->update(['title' => 'Renamed']);
    }

    public function test_an_active_programme_cannot_be_deleted(): void
    {
        $this->seedReferenceData();

        $this->expectException(ValidationException::class);
        $this->programmes()->delete($this->activatedClassProgramme());
    }

    public function test_an_unused_draft_can_be_deleted_with_its_items(): void
    {
        $this->seedReferenceData();
        $programme = $this->classProgramme();
        $this->programmes()->addItem($programme, $this->pathwayItem(1), $this->period('Semester 1'));

        $before = $this->auditCount(AnnualProgrammeItem::class, 'deleted');
        $this->programmes()->delete($programme);

        $this->assertSame(0, AnnualProgramme::count());
        $this->assertSame(0, AnnualProgrammeItem::count());
        $this->assertSame($before + 1, $this->auditCount(AnnualProgrammeItem::class, 'deleted'));
    }

    // -------------------------------------------------------- delete safety

    public function test_a_pathway_used_by_a_programme_cannot_be_deleted(): void
    {
        $this->seedReferenceData();
        $programme = $this->classProgramme();

        // A DRAFT pathway, which its own lifecycle would otherwise allow to be
        // deleted -- so what stops it here is the programme's RESTRICT, which
        // is the thing under test.
        $pathway = $programme->learningPathway;
        $pathway->update(['status' => 'draft']);

        $this->expectException(QueryException::class);
        $pathway->fresh()->delete();
    }

    public function test_an_item_scheduled_in_a_semester_programme_cannot_move_period(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->scheduledProgramme();
        $item = $annual->items()->first();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('already scheduled');

        $this->programmes()->updateItem($item, $this->period('Semester 2'));
    }

    public function test_an_item_scheduled_in_a_semester_programme_cannot_be_removed(): void
    {
        $this->seedReferenceData();
        [$annual] = $this->scheduledProgramme();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Remove those slots first');

        $this->programmes()->removeItem($annual->items()->first());
    }
}
