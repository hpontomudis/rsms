<?php

namespace Tests\Feature;

use App\Livewire\Students\EnglishPlacement as PlacementScreen;
use App\Livewire\TeachingGroups\Show as GroupShow;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\EnglishLevel;
use App\Models\EnglishProgramme;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentEnglishLevelPlacement;
use App\Models\TeachingGroup;
use App\Models\TeachingGroupStudent;
use App\Models\User;
use App\Services\EnglishPlacementService;
use App\Services\StudentGradeResolver;
use App\Services\TeachingGroupMembershipService;
use Database\Seeders\EnglishProgrammeSeeder;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step 2a-ii: teaching groups, membership, and assessed English proficiency.
 *
 * Two facts are modelled separately on purpose and the tests hold that line:
 * which group a student sits in, and what level they were assessed at. They
 * may disagree, and changing one must never rewrite the other.
 */
class TeachingGroupTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year;

    // ---------------------------------------------------------- teaching group

    public function test_a_group_belongs_to_an_academic_year(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A', 'Green');

        $this->assertSame($this->year->id, $group->academicYear->id);
    }

    public function test_an_english_group_references_an_english_level(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A', 'Green');

        $this->assertTrue($group->isEnglishGroup());
        $this->assertSame('Green', $group->englishLevel->name);
        $this->assertSame('Primary English Programme', $group->englishProgramme()->name);
    }

    public function test_a_generic_group_may_have_no_english_level(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Choir');

        $this->assertFalse($group->isEnglishGroup());
        $this->assertNull($group->englishLevel);
        $this->assertNull($group->englishProgramme());
    }

    public function test_group_names_are_unique_within_an_academic_year(): void
    {
        $this->seedReferenceData();
        $this->group('Green A', 'Green');

        $this->expectException(QueryException::class);
        $this->group('Green A', 'Blue');
    }

    public function test_the_same_group_name_may_exist_in_a_different_academic_year(): void
    {
        $this->seedReferenceData();
        $this->group('Green A', 'Green');

        $nextYear = AcademicYear::create([
            'name' => '2027/2028', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'is_current' => false,
        ]);

        $repeat = TeachingGroup::create([
            'academic_year_id' => $nextYear->id,
            'name' => 'Green A',
            'english_level_id' => $this->level('Green')->id,
            'status' => 'active',
        ]);

        $this->assertSame(2, TeachingGroup::where('name', 'Green A')->count());
        $this->assertSame($nextYear->id, $repeat->academic_year_id);
    }

    public function test_an_academic_year_referenced_by_a_group_cannot_be_deleted(): void
    {
        $this->seedReferenceData();
        $this->group('Green A', 'Green');

        $this->expectException(QueryException::class);
        $this->year->delete();
    }

    public function test_an_english_level_referenced_by_a_group_cannot_be_deleted(): void
    {
        $this->seedReferenceData();
        $this->group('Green A', 'Green');

        $this->expectException(QueryException::class);
        $this->level('Green')->delete();
    }

    // -------------------------------------------------------------- membership

    public function test_an_eligible_primary_student_can_join_a_primary_group(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A', 'Green');
        $student = $this->studentInGrade('Year 5');

        $membership = $this->memberships()->add($group, $student, $this->date('2026-08-01'));

        $this->assertTrue($membership->isOpen());
        $this->assertDatabaseHas('teaching_group_student', [
            'teaching_group_id' => $group->id, 'student_id' => $student->id, 'ended_on' => null,
        ]);
    }

    public function test_a_year_8_student_cannot_join_a_primary_group(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A', 'Green');
        $student = $this->studentInGrade('Year 8');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Year 8 is not covered by the Primary English Programme');

        $this->memberships()->add($group, $student, $this->date('2026-08-01'));
    }

    public function test_a_year_5_student_cannot_join_a_junior_high_group(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Level B Group', 'Level B');
        $student = $this->studentInGrade('Year 5');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Year 5 is not covered by the Junior High English Programme');

        $this->memberships()->add($group, $student, $this->date('2026-08-01'));
    }

    public function test_an_eligible_junior_high_student_can_join_a_junior_high_group(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Level B Group', 'Level B');
        $student = $this->studentInGrade('Year 9');

        $membership = $this->memberships()->add($group, $student, $this->date('2026-08-01'));

        $this->assertTrue($membership->isOpen());
    }

    public function test_ending_a_membership_preserves_it_as_history(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A', 'Green');
        $student = $this->studentInGrade('Year 5');

        $membership = $this->memberships()->add($group, $student, $this->date('2026-08-01'));
        $this->memberships()->end($membership, $this->date('2026-12-15'));

        $this->assertSame(1, TeachingGroupStudent::count(), 'the row is closed, not deleted');
        $this->assertSame('2026-12-15', $membership->fresh()->ended_on->toDateString());
    }

    /**
     * The reason this table does not reuse class_student's flat
     * unique(group, student): a student may leave and come back.
     */
    public function test_a_student_can_return_to_a_group_they_previously_left(): void
    {
        $this->seedReferenceData();
        $green = $this->group('Green A', 'Green');
        $blue = $this->group('Blue A', 'Blue');
        $student = $this->studentInGrade('Year 5');

        $first = $this->memberships()->add($green, $student, $this->date('2026-08-01'));
        $this->memberships()->end($first, $this->date('2026-10-31'));

        $second = $this->memberships()->add($blue, $student, $this->date('2026-11-01'));
        $this->memberships()->end($second, $this->date('2027-01-31'));

        $third = $this->memberships()->add($green, $student, $this->date('2027-02-01'));

        $this->assertSame(3, TeachingGroupStudent::count());
        $this->assertSame(2, TeachingGroupStudent::where('teaching_group_id', $green->id)->count());
        $this->assertTrue($third->isOpen());
    }

    public function test_a_second_open_membership_of_the_same_group_is_rejected(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A', 'Green');
        $student = $this->studentInGrade('Year 5');

        $this->memberships()->add($group, $student, $this->date('2026-08-01'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('already an active member');

        $this->memberships()->add($group, $student, $this->date('2026-09-01'));
    }

    /**
     * The partial unique index is the backstop under the service check --
     * proving the database would refuse the row even if the check were bypassed.
     */
    public function test_the_database_itself_rejects_two_open_memberships_of_a_group(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A', 'Green');
        $student = $this->studentInGrade('Year 5');

        TeachingGroupStudent::create([
            'teaching_group_id' => $group->id, 'student_id' => $student->id, 'started_on' => '2026-08-01',
        ]);

        $this->expectException(QueryException::class);
        TeachingGroupStudent::create([
            'teaching_group_id' => $group->id, 'student_id' => $student->id, 'started_on' => '2026-09-01',
        ]);
    }

    public function test_an_end_date_before_the_start_date_is_rejected(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A', 'Green');
        $student = $this->studentInGrade('Year 5');
        $membership = $this->memberships()->add($group, $student, $this->date('2026-09-01'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cannot be before the start date');

        $this->memberships()->end($membership, $this->date('2026-08-01'));
    }

    /**
     * academic_years.start_date and end_date are both NOT NULL, so this is a
     * real boundary rather than an invented one.
     */
    public function test_membership_dates_must_fall_within_the_groups_academic_year(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A', 'Green');
        $student = $this->studentInGrade('Year 5');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('must fall within 2026/2027');

        $this->memberships()->add($group, $student, $this->date('2025-01-01'));
    }

    public function test_a_student_cannot_attend_two_english_groups_in_the_same_programme(): void
    {
        $this->seedReferenceData();
        $green = $this->group('Green A', 'Green');
        $blue = $this->group('Blue A', 'Blue');
        $student = $this->studentInGrade('Year 5');

        $this->memberships()->add($green, $student, $this->date('2026-08-01'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('already in Green A for the Primary English Programme');

        $this->memberships()->add($blue, $student, $this->date('2026-08-01'));
    }

    public function test_generic_group_membership_may_overlap_freely(): void
    {
        $this->seedReferenceData();
        $english = $this->group('Green A', 'Green');
        $choir = $this->group('Choir');
        $chess = $this->group('Chess Club');
        $student = $this->studentInGrade('Year 5');

        $this->memberships()->add($english, $student, $this->date('2026-08-01'));
        $this->memberships()->add($choir, $student, $this->date('2026-08-01'));
        $this->memberships()->add($chess, $student, $this->date('2026-08-01'));

        $this->assertSame(3, TeachingGroupStudent::where('student_id', $student->id)->whereNull('ended_on')->count());
    }

    public function test_a_student_with_no_active_class_cannot_join_an_english_group(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A', 'Green');
        $student = $this->student();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('has no active class');

        $this->memberships()->add($group, $student, $this->date('2026-08-01'));
    }

    // ------------------------------------------------------------- placement

    public function test_a_year_5_student_can_be_placed_at_a_primary_level(): void
    {
        $this->seedReferenceData();
        $student = $this->studentInGrade('Year 5');

        $placement = $this->placements()->place($student, $this->level('Green'), $this->date('2026-08-01'));

        $this->assertTrue($placement->isOpen());
        $this->assertSame('Green', $placement->englishLevel->name);
    }

    public function test_a_year_5_student_cannot_be_placed_at_a_junior_high_level(): void
    {
        $this->seedReferenceData();
        $student = $this->studentInGrade('Year 5');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Year 5 follows the Primary English Programme');

        $this->placements()->place($student, $this->level('Level B'), $this->date('2026-08-01'));
    }

    public function test_a_year_8_student_can_be_placed_at_any_junior_high_level(): void
    {
        $this->seedReferenceData();

        foreach (['Level A', 'Level B', 'Level C'] as $index => $name) {
            $student = $this->studentInGrade('Year 8', "JHS{$index}");
            $placement = $this->placements()->place($student, $this->level($name), $this->date('2026-08-01'));

            $this->assertSame($name, $placement->englishLevel->name);
        }
    }

    public function test_a_year_8_student_cannot_be_placed_at_a_primary_level(): void
    {
        $this->seedReferenceData();
        $student = $this->studentInGrade('Year 8');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Year 8 follows the Junior High English Programme');

        $this->placements()->place($student, $this->level('Blue'), $this->date('2026-08-01'));
    }

    public function test_only_one_placement_is_open_at_a_time(): void
    {
        $this->seedReferenceData();
        $student = $this->studentInGrade('Year 5');

        $this->placements()->place($student, $this->level('Green'), $this->date('2026-08-01'));
        $this->placements()->place($student, $this->level('Blue'), $this->date('2026-12-16'));

        $this->assertSame(1, StudentEnglishLevelPlacement::where('student_id', $student->id)->whereNull('ended_on')->count());
        $this->assertSame('2026-12-15', StudentEnglishLevelPlacement::whereNotNull('ended_on')->first()->ended_on->toDateString());
    }

    public function test_the_database_itself_rejects_two_open_placements(): void
    {
        $this->seedReferenceData();
        $student = $this->studentInGrade('Year 5');

        StudentEnglishLevelPlacement::create([
            'student_id' => $student->id, 'english_level_id' => $this->level('Green')->id, 'started_on' => '2026-08-01',
        ]);

        $this->expectException(QueryException::class);
        StudentEnglishLevelPlacement::create([
            'student_id' => $student->id, 'english_level_id' => $this->level('Blue')->id, 'started_on' => '2026-09-01',
        ]);
    }

    public function test_any_number_of_closed_placements_may_stack_up(): void
    {
        $this->seedReferenceData();
        $student = $this->studentInGrade('Year 5');

        $this->placements()->place($student, $this->level('Purple'), $this->date('2026-08-01'));
        $this->placements()->place($student, $this->level('Pink'), $this->date('2026-09-01'));
        $this->placements()->place($student, $this->level('Gold'), $this->date('2026-10-01'));

        $this->assertSame(2, StudentEnglishLevelPlacement::whereNotNull('ended_on')->count());
        $this->assertSame(1, StudentEnglishLevelPlacement::whereNull('ended_on')->count());
    }

    public function test_the_full_primary_ladder_can_be_represented_historically(): void
    {
        $this->seedReferenceData();
        $student = $this->studentInGrade('Year 5');

        $ladder = ['Purple', 'Pink', 'Gold', 'Green', 'Blue', 'Red'];

        foreach ($ladder as $index => $name) {
            $this->placements()->place($student, $this->level($name), $this->date('2026-08-01')->addMonths($index));
        }

        $this->assertSame(
            $ladder,
            $student->englishPlacements()->with('englishLevel')->get()
                ->sortBy('started_on')->pluck('englishLevel.name')->values()->all()
        );
    }

    public function test_the_junior_high_ladder_can_be_represented_historically(): void
    {
        $this->seedReferenceData();
        $student = $this->studentInGrade('Year 8');

        foreach (['Level A', 'Level B', 'Level C'] as $index => $name) {
            $this->placements()->place($student, $this->level($name), $this->date('2026-08-01')->addMonths($index));
        }

        $this->assertSame(
            ['Level A', 'Level B', 'Level C'],
            $student->englishPlacements()->with('englishLevel')->get()
                ->sortBy('started_on')->pluck('englishLevel.name')->values()->all()
        );
    }

    public function test_assessed_level_and_actual_group_may_disagree(): void
    {
        $this->seedReferenceData();
        $green = $this->group('Green A', 'Green');
        $student = $this->studentInGrade('Year 5');

        $this->memberships()->add($green, $student, $this->date('2026-08-01'));
        $this->placements()->place($student, $this->level('Blue'), $this->date('2026-08-01'));

        $this->assertSame('Blue', $this->placements()->current($student)->englishLevel->name);
        $this->assertSame('Green', $student->teachingGroupMemberships()->whereNull('ended_on')->first()->teachingGroup->englishLevel->name);
    }

    public function test_changing_proficiency_does_not_move_the_student_between_groups(): void
    {
        $this->seedReferenceData();
        $green = $this->group('Green A', 'Green');
        $this->group('Blue A', 'Blue');
        $student = $this->studentInGrade('Year 5');

        $this->memberships()->add($green, $student, $this->date('2026-08-01'));
        $this->placements()->place($student, $this->level('Green'), $this->date('2026-08-01'));

        $before = TeachingGroupStudent::where('student_id', $student->id)->get()->toArray();
        $this->placements()->place($student, $this->level('Blue'), $this->date('2026-12-16'));
        $after = TeachingGroupStudent::where('student_id', $student->id)->get()->toArray();

        $this->assertEquals($before, $after, 'a re-assessment must not silently rewrite group membership');
        $this->assertSame($green->id, TeachingGroupStudent::where('student_id', $student->id)->whereNull('ended_on')->first()->teaching_group_id);
    }

    // ------------------------------------------- academic-year resolution (refinement 1)

    public function test_a_placement_resolves_to_the_academic_year_containing_its_start_date(): void
    {
        $this->seedReferenceData();

        $resolved = app(StudentGradeResolver::class)->yearForDate($this->date('2026-09-01'), $reason);

        $this->assertSame($this->year->id, $resolved->id);
        $this->assertNull($reason);
    }

    /**
     * No fallback to the current year: substituting today's school year for an
     * unmatched historical date would validate against the wrong grade silently.
     */
    public function test_a_date_outside_every_academic_year_is_rejected_rather_than_falling_back(): void
    {
        $this->seedReferenceData();
        $student = $this->studentInGrade('Year 5');

        $this->assertTrue($this->year->is_current, 'precondition: a current year exists to fall back to');

        $resolved = app(StudentGradeResolver::class)->yearForDate($this->date('2020-01-15'), $reason);

        $this->assertNull($resolved);
        $this->assertSame(StudentGradeResolver::NO_YEAR, $reason);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('does not fall within a configured Academic Year');

        $this->placements()->place($student, $this->level('Green'), $this->date('2020-01-15'));
    }

    /**
     * Nothing in the schema stops two academic years overlapping, so the
     * resolver must report it rather than take first().
     */
    public function test_overlapping_academic_years_are_treated_as_ambiguous(): void
    {
        $this->seedReferenceData();
        $student = $this->studentInGrade('Year 5');

        AcademicYear::create([
            'name' => 'Overlapping', 'start_date' => '2026-06-01', 'end_date' => '2027-08-31', 'is_current' => false,
        ]);

        $resolved = app(StudentGradeResolver::class)->yearForDate($this->date('2026-09-01'), $reason);

        $this->assertNull($resolved);
        $this->assertSame(StudentGradeResolver::AMBIGUOUS_YEAR, $reason);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('more than one configured Academic Year');

        $this->placements()->place($student, $this->level('Green'), $this->date('2026-09-01'));
    }

    // ------------------------------------------- placement overlap (refinement 2)

    public function test_a_closed_placement_may_not_overlap_another_closed_placement(): void
    {
        $this->seedReferenceData();
        $student = $this->studentInGrade('Year 5');

        StudentEnglishLevelPlacement::create([
            'student_id' => $student->id, 'english_level_id' => $this->level('Green')->id,
            'started_on' => '2026-07-01', 'ended_on' => '2026-12-31',
        ]);

        // The rule itself, exercised directly: no UI path produces two closed rows.
        $clash = $this->placements()->overlappingPlacement($student, $this->date('2026-10-01'), $this->date('2027-01-31'));

        $this->assertNotNull($clash, 'Oct-Jan overlaps Jul-Dec');
        $this->assertSame('Green', $clash->englishLevel->name);
    }

    public function test_an_open_placement_may_not_overlap_a_closed_placement(): void
    {
        $this->seedReferenceData();
        $student = $this->studentInGrade('Year 5');

        StudentEnglishLevelPlacement::create([
            'student_id' => $student->id, 'english_level_id' => $this->level('Green')->id,
            'started_on' => '2026-07-01', 'ended_on' => '2026-12-31',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Proficiency periods cannot overlap');

        $this->placements()->place($student, $this->level('Blue'), $this->date('2026-10-01'));
    }

    public function test_adjacent_placements_are_allowed(): void
    {
        $this->seedReferenceData();
        $student = $this->studentInGrade('Year 5');

        StudentEnglishLevelPlacement::create([
            'student_id' => $student->id, 'english_level_id' => $this->level('Green')->id,
            'started_on' => '2026-07-01', 'ended_on' => '2026-12-15',
        ]);

        $blue = $this->placements()->place($student, $this->level('Blue'), $this->date('2026-12-16'));

        $this->assertTrue($blue->isOpen());
        $this->assertSame(2, StudentEnglishLevelPlacement::where('student_id', $student->id)->count());
    }

    public function test_the_normal_green_to_blue_progression_leaves_no_gap_and_no_overlap(): void
    {
        $this->seedReferenceData();
        $student = $this->studentInGrade('Year 5');

        $this->placements()->place($student, $this->level('Green'), $this->date('2026-07-01'));
        $this->placements()->place($student, $this->level('Blue'), $this->date('2026-12-16'));

        $green = StudentEnglishLevelPlacement::whereNotNull('ended_on')->firstOrFail();
        $blue = StudentEnglishLevelPlacement::whereNull('ended_on')->firstOrFail();

        $this->assertSame('2026-12-15', $green->ended_on->toDateString());
        $this->assertSame('2026-12-16', $blue->started_on->toDateString());
    }

    public function test_a_backdated_correction_cannot_create_overlapping_proficiency_periods(): void
    {
        $this->seedReferenceData();
        $student = $this->studentInGrade('Year 5');

        $this->placements()->place($student, $this->level('Green'), $this->date('2026-07-01'));
        $this->placements()->place($student, $this->level('Blue'), $this->date('2026-12-16'));

        // Backdated into the closed Green period.
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Proficiency periods cannot overlap');

        $this->placements()->place($student, $this->level('Gold'), $this->date('2026-09-01'));
    }

    // ------------------------------------------ membership overlap (refinement 3)

    public function test_memberships_of_two_groups_in_one_programme_may_not_overlap(): void
    {
        $this->seedReferenceData();
        $green = $this->group('Green A', 'Green');
        $blue = $this->group('Blue A', 'Blue');
        $student = $this->studentInGrade('Year 5');

        TeachingGroupStudent::create([
            'teaching_group_id' => $green->id, 'student_id' => $student->id,
            'started_on' => '2026-07-01', 'ended_on' => '2026-12-31',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Memberships within one programme cannot overlap');

        $this->memberships()->add($blue, $student, $this->date('2026-10-01'));
    }

    public function test_adjacent_memberships_in_one_programme_are_allowed(): void
    {
        $this->seedReferenceData();
        $green = $this->group('Green A', 'Green');
        $blue = $this->group('Blue A', 'Blue');
        $student = $this->studentInGrade('Year 5');

        TeachingGroupStudent::create([
            'teaching_group_id' => $green->id, 'student_id' => $student->id,
            'started_on' => '2026-07-01', 'ended_on' => '2026-12-15',
        ]);

        $membership = $this->memberships()->add($blue, $student, $this->date('2026-12-16'));

        $this->assertTrue($membership->isOpen());
    }

    public function test_the_green_blue_green_sequence_is_allowed_when_the_dates_do_not_overlap(): void
    {
        $this->seedReferenceData();
        $green = $this->group('Green A', 'Green');
        $blue = $this->group('Blue A', 'Blue');
        $student = $this->studentInGrade('Year 5');

        $first = $this->memberships()->add($green, $student, $this->date('2026-07-01'));
        $this->memberships()->end($first, $this->date('2026-10-31'));

        $second = $this->memberships()->add($blue, $student, $this->date('2026-11-01'));
        $this->memberships()->end($second, $this->date('2027-01-31'));

        $third = $this->memberships()->add($green, $student, $this->date('2027-02-01'));

        $this->assertTrue($third->isOpen());
        $this->assertSame(3, TeachingGroupStudent::where('student_id', $student->id)->count());
    }

    public function test_two_historical_memberships_of_the_same_group_may_not_overlap(): void
    {
        $this->seedReferenceData();
        $green = $this->group('Green A', 'Green');
        $student = $this->studentInGrade('Year 5');

        TeachingGroupStudent::create([
            'teaching_group_id' => $green->id, 'student_id' => $student->id,
            'started_on' => '2026-07-01', 'ended_on' => '2026-12-31',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Membership periods in one group cannot overlap');

        $this->memberships()->add($green, $student, $this->date('2026-10-01'));
    }

    public function test_the_same_group_overlap_rule_also_covers_generic_groups(): void
    {
        $this->seedReferenceData();
        $choir = $this->group('Choir');
        $student = $this->studentInGrade('Year 5');

        TeachingGroupStudent::create([
            'teaching_group_id' => $choir->id, 'student_id' => $student->id,
            'started_on' => '2026-07-01', 'ended_on' => '2026-12-31',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Membership periods in one group cannot overlap');

        $this->memberships()->add($choir, $student, $this->date('2026-10-01'));
    }

    public function test_different_generic_groups_may_still_overlap_each_other_and_an_english_group(): void
    {
        $this->seedReferenceData();
        $english = $this->group('Green A', 'Green');
        $choir = $this->group('Choir');
        $chess = $this->group('Chess Club');
        $student = $this->studentInGrade('Year 5');

        $this->memberships()->add($english, $student, $this->date('2026-07-01'));
        $this->memberships()->add($choir, $student, $this->date('2026-07-01'));
        $this->memberships()->add($chess, $student, $this->date('2026-07-01'));

        $this->assertSame(3, TeachingGroupStudent::where('student_id', $student->id)->whereNull('ended_on')->count());
    }

    /**
     * The programme rule must not leak across programmes or across students.
     */
    public function test_the_overlap_rules_do_not_affect_other_programmes_or_other_students(): void
    {
        $this->seedReferenceData();
        $green = $this->group('Green A', 'Green');
        $levelB = $this->group('Level B Group', 'Level B');

        $primaryStudent = $this->studentInGrade('Year 5', 'P5');
        $juniorStudent = $this->studentInGrade('Year 8', 'J8');
        $otherPrimary = $this->studentInGrade('Year 5', 'P5b');

        $this->memberships()->add($green, $primaryStudent, $this->date('2026-07-01'));

        // Same dates, different programme -- unaffected.
        $this->memberships()->add($levelB, $juniorStudent, $this->date('2026-07-01'));
        // Same dates, same group, different student -- unaffected.
        $this->memberships()->add($green, $otherPrimary, $this->date('2026-07-01'));

        $this->assertSame(3, TeachingGroupStudent::whereNull('ended_on')->count());
    }

    public function test_the_picker_hides_a_student_whose_closed_membership_still_covers_the_start_date(): void
    {
        $this->seedReferenceData();
        $green = $this->group('Green A', 'Green');
        $blue = $this->group('Blue A', 'Blue');
        $student = $this->studentInGrade('Year 5');

        TeachingGroupStudent::create([
            'teaching_group_id' => $green->id, 'student_id' => $student->id,
            'started_on' => '2026-07-01', 'ended_on' => '2026-12-31',
        ]);

        $duringGreen = $this->memberships()->eligibleStudents($blue, $this->date('2026-10-01'))->pluck('id');
        $afterGreen = $this->memberships()->eligibleStudents($blue, $this->date('2027-01-01'))->pluck('id');

        $this->assertNotContains($student->id, $duringGreen);
        $this->assertContains($student->id, $afterGreen);
    }

    // ------------------------------------------------------------------ audit

    public function test_group_create_update_and_archive_are_audited(): void
    {
        $this->seedReferenceData();

        $group = $this->group('Green A', 'Green');
        $this->assertSame(1, $this->auditsFor(TeachingGroup::class, 'created'));

        $group->update(['name' => 'Green Group']);
        $group->update(['status' => 'archived']);

        $this->assertSame(2, $this->auditsFor(TeachingGroup::class, 'updated'));
    }

    public function test_adding_and_ending_membership_is_audited(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A', 'Green');
        $student = $this->studentInGrade('Year 5');

        $membership = $this->memberships()->add($group, $student, $this->date('2026-08-01'));
        $this->assertSame(1, $this->auditsFor(TeachingGroupStudent::class, 'created'));

        $this->memberships()->end($membership, $this->date('2026-12-15'));
        $this->assertSame(1, $this->auditsFor(TeachingGroupStudent::class, 'updated'));
    }

    public function test_placement_create_and_close_are_audited(): void
    {
        $this->seedReferenceData();
        $student = $this->studentInGrade('Year 5');

        $this->placements()->place($student, $this->level('Green'), $this->date('2026-08-01'));
        $this->placements()->place($student, $this->level('Blue'), $this->date('2026-12-16'));

        // Two opened, and the first closed by the second.
        $this->assertSame(2, $this->auditsFor(StudentEnglishLevelPlacement::class, 'created'));
        $this->assertSame(1, $this->auditsFor(StudentEnglishLevelPlacement::class, 'updated'));
    }

    // ---------------------------------------------------------- authorization

    public function test_principal_and_admin_staff_can_manage_groups_and_placements(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A', 'Green');

        foreach (['principal', 'admin_staff'] as $role) {
            $user = $this->userWithRole($role);
            $this->assertTrue($user->can('create', TeachingGroup::class), "{$role} should create groups");
            $this->assertTrue($user->can('update', $group), "{$role} should update groups");
            $this->assertTrue($user->can('create', StudentEnglishLevelPlacement::class), "{$role} should record placements");
        }
    }

    public function test_a_teacher_cannot_manage_teaching_groups(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A', 'Green');
        $teacher = $this->userWithRole('teacher');

        $this->assertFalse($teacher->can('create', TeachingGroup::class));
        $this->assertFalse($teacher->can('update', $group));
        $this->assertFalse($teacher->can('create', StudentEnglishLevelPlacement::class));
    }

    /**
     * academics.view must not become a back door to student rosters. Until
     * Step 2b records which teacher teaches which group, there is nothing to
     * scope a teacher's access through, so they get none.
     *
     * Asserted over HTTP rather than against the component, so the real route
     * and middleware stack are what gets tested -- a direct URL, not a hidden
     * button.
     */
    public function test_a_teacher_holding_academics_view_still_cannot_reach_group_rosters(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A', 'Green');
        $teacher = $this->userWithRole('teacher');

        $this->assertTrue($teacher->can('academics.view'), 'precondition: teachers do hold academics.view');

        $this->actingAs($teacher)->get(route('teaching-groups.show', $group))->assertForbidden();
    }

    public function test_a_teacher_cannot_reach_the_group_list_or_a_placement_screen(): void
    {
        $this->seedReferenceData();
        $student = $this->studentInGrade('Year 5');
        $teacher = $this->userWithRole('teacher');

        $this->actingAs($teacher)->get(route('teaching-groups.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('students.english-placement', $student))->assertForbidden();
    }

    public function test_admin_staff_can_reach_those_same_screens(): void
    {
        $this->seedReferenceData();
        $student = $this->studentInGrade('Year 5');
        $admin = $this->userWithRole('admin_staff');

        $this->actingAs($admin)->get(route('teaching-groups.index'))->assertOk();
        $this->actingAs($admin)->get(route('students.english-placement', $student))->assertOk();
    }

    // --------------------------------------------------------------------- UI

    public function test_the_add_student_picker_offers_only_eligible_students(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A', 'Green');
        $eligible = $this->studentInGrade('Year 5', 'P5');
        $wrongPhase = $this->studentInGrade('Year 8', 'J8');

        $offered = Livewire::actingAs($this->userWithRole('admin_staff'))
            ->test(GroupShow::class, ['teachingGroup' => $group])
            ->set('showAddStudent', true)
            ->viewData('eligibleStudents')
            ->pluck('id');

        $this->assertContains($eligible->id, $offered);
        $this->assertNotContains($wrongPhase->id, $offered, 'a Year 8 student must not be offered to a Primary group');
    }

    public function test_the_picker_hides_students_already_in_another_group_of_the_same_programme(): void
    {
        $this->seedReferenceData();
        $green = $this->group('Green A', 'Green');
        $blue = $this->group('Blue A', 'Blue');
        $choir = $this->group('Choir');
        $student = $this->studentInGrade('Year 5');

        $this->memberships()->add($green, $student, $this->date('2026-08-01'));

        $offeredByBlue = Livewire::actingAs($this->userWithRole('admin_staff'))
            ->test(GroupShow::class, ['teachingGroup' => $blue])
            ->set('showAddStudent', true)
            ->viewData('eligibleStudents')->pluck('id');

        $offeredByChoir = Livewire::actingAs($this->userWithRole('admin_staff'))
            ->test(GroupShow::class, ['teachingGroup' => $choir])
            ->set('showAddStudent', true)
            ->viewData('eligibleStudents')->pluck('id');

        $this->assertNotContains($student->id, $offeredByBlue, 'already in a Primary English group');
        $this->assertContains($student->id, $offeredByChoir, 'a generic group has no exclusivity rule');
    }

    public function test_the_group_screen_adds_and_ends_membership(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A', 'Green');
        $student = $this->studentInGrade('Year 5');

        $component = Livewire::actingAs($this->userWithRole('admin_staff'))
            ->test(GroupShow::class, ['teachingGroup' => $group])
            ->set('showAddStudent', true)
            ->set('student_id', (string) $student->id)
            ->set('started_on', '2026-08-01')
            ->call('addStudent');

        $membership = TeachingGroupStudent::where('student_id', $student->id)->firstOrFail();
        $this->assertTrue($membership->isOpen());

        $component->call('startEnding', $membership->id)
            ->set('ended_on', '2026-12-15')
            ->call('endMembership');

        $this->assertSame('2026-12-15', $membership->fresh()->ended_on->toDateString());
    }

    public function test_the_placement_screen_records_a_level(): void
    {
        $this->seedReferenceData();
        $student = $this->studentInGrade('Year 5');

        Livewire::actingAs($this->userWithRole('admin_staff'))
            ->test(PlacementScreen::class, ['student' => $student])
            ->set('showChangeLevel', true)
            ->set('english_level_id', (string) $this->level('Green')->id)
            ->set('started_on', '2026-08-01')
            ->set('placement_reason', 'Start-of-year assessment')
            ->call('placeLevel');

        $this->assertDatabaseHas('student_english_level_placements', [
            'student_id' => $student->id,
            'english_level_id' => $this->level('Green')->id,
            'placement_reason' => 'Start-of-year assessment',
            'ended_on' => null,
        ]);
    }

    public function test_the_placement_screen_offers_only_levels_from_the_students_programme(): void
    {
        $this->seedReferenceData();
        $student = $this->studentInGrade('Year 8');

        $offered = Livewire::actingAs($this->userWithRole('admin_staff'))
            ->test(PlacementScreen::class, ['student' => $student])
            ->viewData('eligibleLevels')
            ->pluck('name');

        $this->assertEquals(['Level A', 'Level B', 'Level C'], $offered->all());
    }

    public function test_an_archived_group_refuses_new_students(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A', 'Green');
        $group->update(['status' => 'archived']);
        $student = $this->studentInGrade('Year 5');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('archived and cannot take new students');

        $this->memberships()->add($group->fresh(), $student, $this->date('2026-08-01'));
    }

    // ---------------------------------------------------------------- helpers

    private function seedReferenceData(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(GradeSeeder::class);
        $this->seed(EnglishProgrammeSeeder::class);

        $this->year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_current' => true,
        ]);
    }

    private function group(string $name, ?string $levelName = null): TeachingGroup
    {
        return TeachingGroup::create([
            'academic_year_id' => $this->year->id,
            'name' => $name,
            'english_level_id' => $levelName ? $this->level($levelName)->id : null,
            'status' => 'active',
        ]);
    }

    private function level(string $name): EnglishLevel
    {
        return EnglishLevel::where('name', $name)->firstOrFail();
    }

    /**
     * A student enrolled in an active class of the given grade, for the
     * current academic year -- the only route by which the application knows
     * a student's grade.
     */
    private function studentInGrade(string $gradeName, string $classSuffix = 'A'): Student
    {
        $grade = Grade::where('name', $gradeName)->firstOrFail();

        $class = SchoolClass::firstOrCreate(
            ['name' => "{$gradeName} {$classSuffix}", 'academic_year_id' => $this->year->id],
            ['grade_id' => $grade->id]
        );

        $student = $this->student();

        $class->students()->attach($student->id, [
            'enrolled_at' => $this->year->start_date,
            'status' => 'active',
        ]);

        return $student;
    }

    /**
     * A student with no class membership at all -- the case where the
     * application cannot determine a grade.
     */
    private function student(): Student
    {
        static $n = 0;
        $n++;

        return Student::create([
            'student_number' => sprintf('STU-%03d', $n),
            'first_name' => 'Student',
            'last_name' => (string) $n,
            'date_of_birth' => '2015-03-12',
            'gender' => 'male',
            'enrollment_date' => '2026-07-01',
            'status' => 'active',
        ]);
    }

    private function memberships(): TeachingGroupMembershipService
    {
        return app(TeachingGroupMembershipService::class);
    }

    private function placements(): EnglishPlacementService
    {
        return app(EnglishPlacementService::class);
    }

    private function date(string $date): Carbon
    {
        return Carbon::parse($date);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function auditsFor(string $type, string $action): int
    {
        return AuditLog::where('auditable_type', $type)->where('action', $action)->count();
    }
}
