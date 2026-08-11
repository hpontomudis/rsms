<?php

namespace Tests\Feature;

use App\Livewire\Assessments\Create as AssessmentCreate;
use App\Livewire\Assessments\Show as AssessmentShow;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\ClassSubject;
use App\Models\EnglishLevel;
use App\Models\Grade;
use App\Models\Position;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingGroup;
use App\Models\User;
use Database\Seeders\EnglishProgrammeSeeder;
use Database\Seeders\GradeSeeder;
use Database\Seeders\PositionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step 2c: one assessment engine for both roster sources.
 *
 * A group-backed teaching assignment must reach `assessments` and
 * `assessment_results` through exactly the same path a class-backed one does.
 * Nothing here touches ReportCard.
 */
class TeachingAssignmentAssessmentTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year;

    private Subject $english;

    private Subject $maths;

    // ------------------------------------------------------ unified accessors

    public function test_a_class_backed_assignment_resolves_its_class_roster(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);
        $this->student('Outsider', $this->schoolClass('Year 5', 'Year 5B'));

        $assignment = $this->classAssignment($class, $this->maths);

        $this->assertSame([$andi->id], $assignment->rosterStudentIdsOn($this->date('2026-11-01'))->all());
    }

    public function test_a_group_backed_assignment_resolves_its_group_roster(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);
        $this->student('NotInGroup', $class);

        $green = $this->group('Green A', 'Green');
        $this->join($green, $andi, '2026-07-01');

        $assignment = $this->groupAssignment($green, $this->english);

        $this->assertSame([$andi->id], $assignment->rosterStudentIdsOn($this->date('2026-11-01'))->all());
    }

    public function test_a_class_backed_assignment_resolves_its_academic_year(): void
    {
        $this->seedReferenceData();
        $assignment = $this->classAssignment($this->schoolClass('Year 5', 'Year 5A'), $this->maths);

        $this->assertSame($this->year->id, $assignment->academicYear()->id);
    }

    public function test_a_group_backed_assignment_resolves_its_academic_year(): void
    {
        $this->seedReferenceData();
        $assignment = $this->groupAssignment($this->group('Green A', 'Green'), $this->english);

        $this->assertSame($this->year->id, $assignment->academicYear()->id);
    }

    public function test_display_names_and_labels_distinguish_the_two_sources(): void
    {
        $this->seedReferenceData();
        $classBacked = $this->classAssignment($this->schoolClass('Year 5', 'Year 5A'), $this->maths);
        $groupBacked = $this->groupAssignment($this->group('Green A', 'Green'), $this->english);

        $this->assertSame('Year 5A', $classBacked->displayName());
        $this->assertSame('Class', $classBacked->rosterLabel());

        $this->assertSame('Green A', $groupBacked->displayName());
        $this->assertSame('Teaching Group', $groupBacked->rosterLabel(), 'Green A must not be called a class');
    }

    public function test_an_assignment_with_no_roster_source_is_still_impossible(): void
    {
        $this->seedReferenceData();

        $this->expectException(QueryException::class);

        ClassSubject::create([
            'class_id' => null, 'teaching_group_id' => null,
            'subject_id' => $this->english->id, 'staff_id' => $this->teacher('Eka')->id,
            'started_on' => '2026-07-01',
        ]);
    }

    // ----------------------------------------------------- assessment creation

    public function test_a_class_assignment_can_still_create_an_assessment(): void
    {
        $this->seedReferenceData();
        $assignment = $this->classAssignment($this->schoolClass('Year 5', 'Year 5A'), $this->maths);

        Livewire::actingAs($this->admin())
            ->test(AssessmentCreate::class, ['class_subject_id' => (string) $assignment->id])
            ->set('name', 'Quiz 1')
            ->set('academic_period_id', (string) $this->period('Semester 1')->id)
            ->set('max_score', '100')
            ->set('assessment_date', '2026-11-01')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('assessments', ['class_subject_id' => $assignment->id, 'name' => 'Quiz 1']);
    }

    public function test_a_group_assignment_can_create_an_assessment(): void
    {
        $this->seedReferenceData();
        $assignment = $this->groupAssignment($this->group('Green A', 'Green'), $this->english);

        Livewire::actingAs($this->admin())
            ->test(AssessmentCreate::class, ['class_subject_id' => (string) $assignment->id])
            ->set('name', 'Reading Check')
            ->set('academic_period_id', (string) $this->period('Semester 1')->id)
            ->set('max_score', '100')
            ->set('assessment_date', '2026-11-01')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('assessments', ['class_subject_id' => $assignment->id, 'name' => 'Reading Check']);
    }

    public function test_a_group_assessment_offers_only_periods_from_the_groups_academic_year(): void
    {
        $this->seedReferenceData();

        $otherYear = AcademicYear::create([
            'name' => '2027/2028', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'is_current' => false,
        ]);
        $foreignPeriod = AcademicPeriod::create([
            'academic_year_id' => $otherYear->id, 'name' => 'Semester 1', 'sequence' => 1,
            'start_date' => '2027-07-01', 'end_date' => '2027-12-31',
        ]);

        $assignment = $this->groupAssignment($this->group('Green A', 'Green'), $this->english);

        $offered = Livewire::actingAs($this->admin())
            ->test(AssessmentCreate::class, ['class_subject_id' => (string) $assignment->id])
            ->viewData('periods');

        $this->assertSame(['Semester 1', 'Semester 2'], $offered->pluck('name')->all());
        $this->assertNotContains($foreignPeriod->id, $offered->pluck('id')->all());

        // And the period rule rejects it server-side, not just in the dropdown.
        Livewire::actingAs($this->admin())
            ->test(AssessmentCreate::class, ['class_subject_id' => (string) $assignment->id])
            ->set('name', 'Wrong year')
            ->set('academic_period_id', (string) $foreignPeriod->id)
            ->set('max_score', '100')
            ->set('assessment_date', '2026-11-01')
            ->call('save')
            ->assertHasErrors('academic_period_id');
    }

    public function test_a_closed_group_assignment_cannot_create_a_new_assessment(): void
    {
        $this->seedReferenceData();
        $assignment = $this->groupAssignment($this->group('Green A', 'Green'), $this->english);
        $assignment->update(['ended_on' => '2026-12-15']);

        $this->assertFalse($this->admin()->can('createFor', [Assessment::class, $assignment->fresh()]));
    }

    public function test_an_archived_group_assignment_keeps_its_history_readable(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A', 'Green');
        $assignment = $this->groupAssignment($group, $this->english);
        $assessment = $this->assessment($assignment, '2026-11-01');

        $group->update(['status' => 'archived']);

        $this->assertTrue($this->admin()->can('view', $assessment->fresh()), 'archiving must not hide history');
    }

    public function test_an_unassigned_teacher_cannot_create_a_group_assessment(): void
    {
        $this->seedReferenceData();
        $assignment = $this->groupAssignment($this->group('Green A', 'Green'), $this->english, $this->teacher('Eka'));

        $other = $this->teacherUser('Rina');

        $this->assertFalse($other->can('createFor', [Assessment::class, $assignment]));
    }

    // ---------------------------------------------------------------- roster

    public function test_a_class_assessment_roster_is_unchanged(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);
        $outsider = $this->student('Outsider', $this->schoolClass('Year 5', 'Year 5B'));

        $assessment = $this->assessment($this->classAssignment($class, $this->maths), '2026-11-01');

        $ids = $assessment->scoreSheetStudents()->pluck('id')->all();

        $this->assertContains($andi->id, $ids);
        $this->assertNotContains($outsider->id, $ids);
    }

    public function test_a_group_assessment_roster_is_its_members_on_the_assessment_date(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);
        $budi = $this->student('Budi', $class);

        $green = $this->group('Green A', 'Green');
        $this->join($green, $andi, '2026-07-01', '2026-12-15');
        $this->join($green, $budi, '2026-07-01');

        $assignment = $this->groupAssignment($green, $this->english);

        $november = $this->assessment($assignment, '2026-11-01');
        $this->assertEqualsCanonicalizing([$andi->id, $budi->id], $november->scoreSheetStudents()->pluck('id')->all());

        $december = $this->assessment($assignment, '2026-12-20', 'Late test');
        $this->assertSame([$budi->id], $december->scoreSheetStudents()->pluck('id')->all(), 'Andi left on 15 Dec');
    }

    public function test_a_same_grade_student_who_is_not_in_the_group_is_excluded(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);
        $sameGrade = $this->student('SameGrade', $class);

        $green = $this->group('Green A', 'Green');
        $this->join($green, $andi, '2026-07-01');

        $assessment = $this->assessment($this->groupAssignment($green, $this->english), '2026-11-01');

        $this->assertNotContains(
            $sameGrade->id,
            $assessment->scoreSheetStudents()->pluck('id')->all(),
            'sharing a grade is not membership'
        );
    }

    public function test_a_member_of_another_english_group_is_excluded(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);
        $blueStudent = $this->student('BlueOne', $class);

        $green = $this->group('Green A', 'Green');
        $blue = $this->group('Blue A', 'Blue');
        $this->join($green, $andi, '2026-07-01');
        $this->join($blue, $blueStudent, '2026-07-01');

        $assessment = $this->assessment($this->groupAssignment($green, $this->english), '2026-11-01');

        $this->assertSame([$andi->id], $assessment->scoreSheetStudents()->pluck('id')->all());
    }

    public function test_an_existing_result_keeps_a_student_on_the_sheet_after_they_leave(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);

        $green = $this->group('Green A', 'Green');
        $membership = $this->join($green, $andi, '2026-07-01');

        $assessment = $this->assessment($this->groupAssignment($green, $this->english), '2026-11-01');
        AssessmentResult::create(['assessment_id' => $assessment->id, 'student_id' => $andi->id, 'score' => 85]);

        // Andi leaves Green A after the assessment.
        $membership->update(['ended_on' => '2026-11-30']);

        $sheet = $assessment->fresh()->scoreSheetStudents();

        $this->assertContains($andi->id, $sheet->pluck('id')->all(), 'a recorded mark does not stop being true');
    }

    public function test_a_departed_students_existing_result_is_still_editable_by_an_admin(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);

        $green = $this->group('Green A', 'Green');
        $membership = $this->join($green, $andi, '2026-07-01');

        $assessment = $this->assessment($this->groupAssignment($green, $this->english), '2026-11-01');
        AssessmentResult::create(['assessment_id' => $assessment->id, 'student_id' => $andi->id, 'score' => 85]);
        $membership->update(['ended_on' => '2026-11-30']);

        Livewire::actingAs($this->admin())
            ->test(AssessmentShow::class, ['assessment' => $assessment])
            ->set("scores.{$andi->id}", '90')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(90, AssessmentResult::where('student_id', $andi->id)->first()->score);
    }

    public function test_a_student_outside_the_roster_cannot_be_given_a_score(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);
        $stranger = $this->student('Stranger', $class);

        $green = $this->group('Green A', 'Green');
        $this->join($green, $andi, '2026-07-01');

        $assessment = $this->assessment($this->groupAssignment($green, $this->english), '2026-11-01');

        // A tampered payload naming a student who was never on this roster.
        Livewire::actingAs($this->admin())
            ->test(AssessmentShow::class, ['assessment' => $assessment])
            ->set("scores.{$stranger->id}", '99')
            ->call('save');

        $this->assertDatabaseMissing('assessment_results', [
            'assessment_id' => $assessment->id, 'student_id' => $stranger->id,
        ]);
    }

    // ----------------------------------------------------------- score engine

    public function test_a_group_assessment_writes_into_assessment_results(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);

        $green = $this->group('Green A', 'Green');
        $this->join($green, $andi, '2026-07-01');

        $assessment = $this->assessment($this->groupAssignment($green, $this->english), '2026-11-01');

        Livewire::actingAs($this->admin())
            ->test(AssessmentShow::class, ['assessment' => $assessment])
            ->set("scores.{$andi->id}", '78')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('assessment_results', [
            'assessment_id' => $assessment->id, 'student_id' => $andi->id,
        ]);
        $this->assertEquals(78, AssessmentResult::first()->score);
    }

    public function test_score_validation_is_identical_for_group_backed_assessments(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);

        $green = $this->group('Green A', 'Green');
        $this->join($green, $andi, '2026-07-01');

        $assessment = $this->assessment($this->groupAssignment($green, $this->english), '2026-11-01');

        Livewire::actingAs($this->admin())
            ->test(AssessmentShow::class, ['assessment' => $assessment])
            ->set("scores.{$andi->id}", '150')
            ->call('save')
            ->assertHasErrors("scores.{$andi->id}");
    }

    public function test_assessment_results_is_the_only_score_store(): void
    {
        $forbidden = ['english_results', 'teaching_group_results', 'english_assessment_results', 'teaching_group_assessments', 'english_assessments'];

        foreach ($forbidden as $table) {
            $this->assertFalse(Schema::hasTable($table), "{$table} must not exist -- one score store only");
        }

        $this->assertTrue(Schema::hasTable('assessment_results'));
    }

    public function test_one_result_per_student_per_assessment_is_still_enforced(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);

        $green = $this->group('Green A', 'Green');
        $this->join($green, $andi, '2026-07-01');
        $assessment = $this->assessment($this->groupAssignment($green, $this->english), '2026-11-01');

        AssessmentResult::create(['assessment_id' => $assessment->id, 'student_id' => $andi->id, 'score' => 70]);

        $this->expectException(QueryException::class);
        AssessmentResult::create(['assessment_id' => $assessment->id, 'student_id' => $andi->id, 'score' => 80]);
    }

    // -------------------------------------------------------- teacher scoping

    public function test_the_assigned_group_teacher_can_score_their_own_group(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);

        $green = $this->group('Green A', 'Green');
        $this->join($green, $andi, '2026-07-01');

        $eka = $this->teacher('Eka');
        $ekaUser = $this->teacherUser('Eka', $eka);
        $assessment = $this->assessment($this->groupAssignment($green, $this->english, $eka), '2026-11-01');

        Livewire::actingAs($ekaUser)
            ->test(AssessmentShow::class, ['assessment' => $assessment])
            ->set("scores.{$andi->id}", '88')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(88, AssessmentResult::first()->score);
    }

    public function test_a_group_teacher_cannot_score_a_student_from_another_group(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);
        $blueStudent = $this->student('BlueOne', $class);

        $green = $this->group('Green A', 'Green');
        $blue = $this->group('Blue A', 'Blue');
        $this->join($green, $andi, '2026-07-01');
        $this->join($blue, $blueStudent, '2026-07-01');

        $eka = $this->teacher('Eka');
        $ekaUser = $this->teacherUser('Eka', $eka);
        $assessment = $this->assessment($this->groupAssignment($green, $this->english, $eka), '2026-11-01');

        Livewire::actingAs($ekaUser)
            ->test(AssessmentShow::class, ['assessment' => $assessment])
            ->set("scores.{$blueStudent->id}", '99')
            ->call('save');

        $this->assertDatabaseMissing('assessment_results', ['student_id' => $blueStudent->id]);
    }

    public function test_a_group_teacher_cannot_score_an_unrelated_same_grade_student(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);
        $sameGrade = $this->student('SameGrade', $class);

        $green = $this->group('Green A', 'Green');
        $this->join($green, $andi, '2026-07-01');

        $eka = $this->teacher('Eka');
        $ekaUser = $this->teacherUser('Eka', $eka);
        $assessment = $this->assessment($this->groupAssignment($green, $this->english, $eka), '2026-11-01');

        Livewire::actingAs($ekaUser)
            ->test(AssessmentShow::class, ['assessment' => $assessment])
            ->set("scores.{$sameGrade->id}", '99')
            ->call('save');

        $this->assertDatabaseMissing('assessment_results', ['student_id' => $sameGrade->id]);
    }

    public function test_a_teacher_loses_write_access_once_the_group_assignment_closes(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);

        $green = $this->group('Green A', 'Green');
        $this->join($green, $andi, '2026-07-01');

        $eka = $this->teacher('Eka');
        $ekaUser = $this->teacherUser('Eka', $eka);
        $assignment = $this->groupAssignment($green, $this->english, $eka);
        $assessment = $this->assessment($assignment, '2026-11-01');

        $assignment->update(['ended_on' => '2026-12-15']);

        $this->assertTrue($ekaUser->can('view', $assessment->fresh()), 'history stays readable');
        $this->assertFalse($ekaUser->can('recordScores', $assessment->fresh()), 'but not writable');
        $this->assertFalse($ekaUser->can('createFor', [Assessment::class, $assignment->fresh()]));
    }

    /**
     * Step 2c changes nothing for class-backed teacher scoping.
     */
    public function test_class_backed_teacher_scoping_is_unchanged(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);

        $sarah = $this->teacher('Sarah');
        $sarahUser = $this->teacherUser('Sarah', $sarah);
        $assignment = $this->classAssignment($class, $this->maths, $sarah);
        $assessment = $this->assessment($assignment, '2026-11-01');

        Livewire::actingAs($sarahUser)
            ->test(AssessmentShow::class, ['assessment' => $assessment])
            ->set("scores.{$andi->id}", '72')
            ->call('save')
            ->assertHasNoErrors();

        $otherAssignment = $this->classAssignment($this->schoolClass('Year 5', 'Year 5B'), $this->maths, $this->teacher('Eka'));
        $this->assertFalse($sarahUser->can('createFor', [Assessment::class, $otherAssignment]));
    }

    /**
     * Step 2c stored group-backed results correctly but left them off the
     * report card; Step 2d connected them. Kept here as the seam between the
     * two steps: a score entered through the group workflow must reach the
     * report card without any second score store.
     */
    public function test_a_group_backed_result_reaches_the_report_card(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);

        $green = $this->group('Green A', 'Green');
        $this->join($green, $andi, '2026-07-01');
        $assessment = $this->assessment($this->groupAssignment($green, $this->english), '2026-11-01');
        AssessmentResult::create(['assessment_id' => $assessment->id, 'student_id' => $andi->id, 'score' => 85]);

        $rows = Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Academics\ReportCard::class, ['student' => $andi])
            ->set('academic_year_id', (string) $this->year->id)
            ->viewData('rows');

        $this->assertContains(
            'English',
            $rows->pluck('subject.name')->all(),
            'the group-backed English result must be discoverable (Step 2d)'
        );
    }

    // ---------------------------------------------------------------- helpers

    private function seedReferenceData(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(GradeSeeder::class);
        $this->seed(PositionSeeder::class);

        $this->year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-07-01',
            'end_date' => '2027-06-30', 'is_current' => true,
        ]);

        AcademicPeriod::create([
            'academic_year_id' => $this->year->id, 'name' => 'Semester 1', 'sequence' => 1,
            'start_date' => '2026-07-01', 'end_date' => '2026-12-31',
        ]);
        AcademicPeriod::create([
            'academic_year_id' => $this->year->id, 'name' => 'Semester 2', 'sequence' => 2,
            'start_date' => '2027-01-01', 'end_date' => '2027-06-30',
        ]);

        $this->seed(EnglishProgrammeSeeder::class);

        $this->english = Subject::create(['name' => 'English']);
        $this->maths = Subject::create(['name' => 'Mathematics']);
    }

    private function period(string $name): AcademicPeriod
    {
        return AcademicPeriod::where('academic_year_id', $this->year->id)->where('name', $name)->firstOrFail();
    }

    private function schoolClass(string $grade, string $name): SchoolClass
    {
        return SchoolClass::firstOrCreate([
            'name' => $name,
            'grade_id' => Grade::where('name', $grade)->firstOrFail()->id,
            'academic_year_id' => $this->year->id,
        ]);
    }

    private function student(string $first, SchoolClass $class): Student
    {
        $student = Student::create([
            'student_number' => 'S-'.strtoupper($first), 'first_name' => $first, 'last_name' => 'Test',
            'date_of_birth' => '2015-01-01', 'gender' => 'male',
            'enrollment_date' => '2026-07-01', 'status' => 'active',
        ]);

        $class->students()->attach($student->id, ['enrolled_at' => '2026-07-01', 'status' => 'active']);

        return $student;
    }

    private function group(string $name, string $level): TeachingGroup
    {
        return TeachingGroup::firstOrCreate(
            ['academic_year_id' => $this->year->id, 'name' => $name],
            ['english_level_id' => EnglishLevel::where('name', $level)->firstOrFail()->id, 'status' => 'active'],
        );
    }

    private function join(TeachingGroup $group, Student $student, string $from, ?string $to = null)
    {
        return $group->memberships()->create([
            'student_id' => $student->id, 'started_on' => $from, 'ended_on' => $to,
        ]);
    }

    private function classAssignment(SchoolClass $class, Subject $subject, ?Staff $teacher = null): ClassSubject
    {
        return ClassSubject::create([
            'class_id' => $class->id, 'subject_id' => $subject->id,
            'staff_id' => ($teacher ?? $this->teacher('Sarah'))->id, 'started_on' => '2026-07-01',
        ]);
    }

    private function groupAssignment(TeachingGroup $group, Subject $subject, ?Staff $teacher = null): ClassSubject
    {
        return ClassSubject::create([
            'teaching_group_id' => $group->id, 'subject_id' => $subject->id,
            'staff_id' => ($teacher ?? $this->teacher('Eka'))->id, 'started_on' => '2026-07-01',
        ]);
    }

    private function assessment(ClassSubject $assignment, string $date, string $name = 'Test'): Assessment
    {
        return Assessment::create([
            'class_subject_id' => $assignment->id,
            'academic_period_id' => $this->period('Semester 1')->id,
            'name' => $name, 'max_score' => 100, 'assessment_date' => $date,
        ]);
    }

    private function teacher(string $first): Staff
    {
        return Staff::firstOrCreate(
            ['staff_number' => 'T-'.strtoupper($first)],
            [
                'first_name' => $first, 'last_name' => 'Teacher',
                'position_id' => Position::firstOrFail()->id, 'phone' => '0800000000',
                'hire_date' => '2020-01-01', 'status' => 'active',
            ],
        );
    }

    private function teacherUser(string $first, ?Staff $staff = null): User
    {
        $user = User::create([
            'name' => $first, 'email' => strtolower($first).'@rahai.test',
            'password' => bcrypt('password'), 'status' => 'active',
        ]);
        $user->assignRole('teacher');

        ($staff ?? $this->teacher($first))->update(['user_id' => $user->id]);

        return $user->fresh();
    }

    private function admin(): User
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@rahai.test'],
            ['name' => 'Admin', 'password' => bcrypt('password'), 'status' => 'active'],
        );

        if (! $user->hasRole('admin_staff')) {
            $user->assignRole('admin_staff');
        }

        return $user->fresh();
    }

    private function date(string $value): Carbon
    {
        return Carbon::parse($value);
    }
}
