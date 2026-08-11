<?php

namespace Tests\Feature;

use App\Livewire\Academics\ReportCard;
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
use App\Models\StudentEnglishLevelPlacement;
use App\Models\Subject;
use App\Models\TeachingGroup;
use App\Models\User;
use Database\Seeders\EnglishProgrammeSeeder;
use Database\Seeders\GradeSeeder;
use Database\Seeders\PositionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step 2d: the report card discovers academic participation from BOTH
 * administrative classes and teaching groups.
 *
 * The load-bearing idea is the union of two paths -- what the student was
 * scored on, and what the student took part in -- so that moving between
 * groups never erases history, and a subject with no marks yet still shows up.
 */
class ReportCardDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year;

    private Subject $english;

    private Subject $maths;

    // -------------------------------------------------- existing class behaviour

    public function test_a_class_backed_result_is_unchanged(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);

        $assignment = $this->classAssignment($class, $this->maths);
        $this->score($andi, $this->assessment($assignment, 'Semester 1', '2026-11-01'), 85);

        $row = $this->rowFor($andi, 'Mathematics');

        $this->assertEquals(85, $row->periodAverages[$this->period('Semester 1')->id]);
        $this->assertNull($row->periodAverages[$this->period('Semester 2')->id]);
        $this->assertEquals(85, $row->overall);
    }

    public function test_a_class_backed_subject_with_no_result_still_renders_empty(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);

        $this->classAssignment($class, $this->maths);

        $row = $this->rowFor($andi, 'Mathematics');

        $this->assertNull($row->overall);
        $this->assertNull($row->periodAverages[$this->period('Semester 1')->id]);
    }

    public function test_a_class_teacher_handover_still_renders_the_subject_once(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);

        $first = $this->classAssignment($class, $this->maths, $this->teacher('Sarah'));
        $this->score($andi, $this->assessment($first, 'Semester 1', '2026-11-01'), 80);
        $first->update(['ended_on' => '2026-12-15']);

        $second = $this->classAssignment($class, $this->maths, $this->teacher('Eka'), '2026-12-16');
        $this->score($andi, $this->assessment($second, 'Semester 2', '2027-02-01'), 90);

        $rows = $this->rows($andi);

        $this->assertSame(1, $rows->where('subject.name', 'Mathematics')->count());
        $row = $this->rowFor($andi, 'Mathematics');
        $this->assertEquals(80, $row->periodAverages[$this->period('Semester 1')->id]);
        $this->assertEquals(90, $row->periodAverages[$this->period('Semester 2')->id]);
        $this->assertEquals(85, $row->overall);
    }

    public function test_period_averages_are_the_mean_of_percentages_within_that_period(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);
        $assignment = $this->classAssignment($class, $this->maths);

        // 80/100 and 30/50 (= 60%) in the same period -> mean 70.
        $this->score($andi, $this->assessment($assignment, 'Semester 1', '2026-11-01', 'A', 100), 80);
        $this->score($andi, $this->assessment($assignment, 'Semester 1', '2026-11-02', 'B', 50), 30);

        $row = $this->rowFor($andi, 'Mathematics');

        $this->assertEquals(70, $row->periodAverages[$this->period('Semester 1')->id]);
    }

    // --------------------------------------------------- teaching-group discovery

    public function test_a_group_backed_result_appears_on_the_report_card(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);

        $green = $this->group('Green A', 'Green');
        $this->join($green, $andi, '2026-07-01');
        $this->score($andi, $this->assessment($this->groupAssignment($green, $this->english), 'Semester 1', '2026-11-01'), 85);

        $this->assertEquals(85, $this->rowFor($andi, 'English')->overall);
    }

    public function test_a_same_grade_non_member_receives_no_group_subject(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);
        $bystander = $this->student('Bystander', $class);

        $green = $this->group('Green A', 'Green');
        $this->join($green, $andi, '2026-07-01');
        $this->groupAssignment($green, $this->english);

        $this->assertNotContains('English', $this->rows($bystander)->pluck('subject.name')->all());
    }

    public function test_a_member_of_another_group_does_not_leak_in(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);
        $blueStudent = $this->student('BlueOne', $class);

        $green = $this->group('Green A', 'Green');
        $blue = $this->group('Blue A', 'Blue');
        $this->join($green, $andi, '2026-07-01');
        $this->join($blue, $blueStudent, '2026-07-01');

        $greenAssignment = $this->groupAssignment($green, $this->english);
        $blueAssignment = $this->groupAssignment($blue, $this->english, $this->teacher('Eka'));

        $this->score($andi, $this->assessment($greenAssignment, 'Semester 1', '2026-11-01'), 85);
        $this->score($blueStudent, $this->assessment($blueAssignment, 'Semester 1', '2026-11-01', 'Blue test'), 50);

        // Andi's English must come only from Green A, never from Blue A's assessment.
        $this->assertEquals(85, $this->rowFor($andi, 'English')->overall);
        $this->assertEquals(50, $this->rowFor($blueStudent, 'English')->overall);
    }

    public function test_an_archived_group_keeps_its_historical_result_visible(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);

        $green = $this->group('Green A', 'Green');
        $this->join($green, $andi, '2026-07-01');
        $this->score($andi, $this->assessment($this->groupAssignment($green, $this->english), 'Semester 1', '2026-11-01'), 85);

        $green->update(['status' => 'archived']);

        $this->assertEquals(85, $this->rowFor($andi, 'English')->overall, 'archived means no new activity, not erased history');
    }

    public function test_a_closed_assignment_keeps_its_historical_result_visible(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);

        $green = $this->group('Green A', 'Green');
        $this->join($green, $andi, '2026-07-01');
        $assignment = $this->groupAssignment($green, $this->english);
        $this->score($andi, $this->assessment($assignment, 'Semester 1', '2026-11-01'), 85);

        $assignment->update(['ended_on' => '2026-12-15']);

        $this->assertEquals(85, $this->rowFor($andi, 'English')->overall);
    }

    // ------------------------------------------------------- Green -> Blue move

    public function test_a_mid_year_group_move_produces_one_english_row_with_both_periods(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);

        $green = $this->group('Green A', 'Green');
        $blue = $this->group('Blue A', 'Blue');

        $greenMembership = $this->join($green, $andi, '2026-07-01');
        $greenAssignment = $this->groupAssignment($green, $this->english, $this->teacher('Sarah'));
        $this->score($andi, $this->assessment($greenAssignment, 'Semester 1', '2026-11-01', 'Green S1'), 85);

        // Student moves in December.
        $greenMembership->update(['ended_on' => '2026-12-15']);
        $this->join($blue, $andi, '2026-12-16');
        $blueAssignment = $this->groupAssignment($blue, $this->english, $this->teacher('Eka'));
        $this->score($andi, $this->assessment($blueAssignment, 'Semester 2', '2027-02-01', 'Blue S2'), 90);

        $rows = $this->rows($andi);
        $this->assertSame(1, $rows->where('subject.name', 'English')->count(), 'English renders exactly once');

        $row = $this->rowFor($andi, 'English');
        $this->assertEquals(85, $row->periodAverages[$this->period('Semester 1')->id], 'Semester 1 comes from Green A');
        $this->assertEquals(90, $row->periodAverages[$this->period('Semester 2')->id], 'Semester 2 comes from Blue A');

        // Flat mean over both results: (85 + 90) / 2.
        $this->assertEquals(88, $row->overall);
    }

    public function test_the_earlier_group_result_survives_even_with_no_remaining_membership(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);

        $green = $this->group('Green A', 'Green');
        $membership = $this->join($green, $andi, '2026-07-01');
        $this->score($andi, $this->assessment($this->groupAssignment($green, $this->english), 'Semester 1', '2026-11-01'), 85);

        // Membership deleted outright -- only the result remains to find it by.
        $membership->delete();

        $this->assertEquals(85, $this->rowFor($andi, 'English')->overall, 'the result-driven path must stand alone');
    }

    public function test_a_score_discovered_by_both_paths_is_not_counted_twice(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);

        // Current membership AND a recorded result: both discovery paths hit
        // the same assignment.
        $green = $this->group('Green A', 'Green');
        $this->join($green, $andi, '2026-07-01');
        $assignment = $this->groupAssignment($green, $this->english);
        $this->score($andi, $this->assessment($assignment, 'Semester 1', '2026-11-01', 'A', 100), 80);
        $this->score($andi, $this->assessment($assignment, 'Semester 1', '2026-11-02', 'B', 100), 60);

        $rows = $this->rows($andi);

        $this->assertSame(1, $rows->where('subject.name', 'English')->count());
        // Mean of 80 and 60. If either result were counted twice the mean
        // would still be 70, so assert the underlying result count as well.
        $this->assertEquals(70, $this->rowFor($andi, 'English')->overall);
        $this->assertSame(2, AssessmentResult::where('student_id', $andi->id)->count());
    }

    public function test_a_teacher_handover_within_one_group_renders_english_once(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);

        $green = $this->group('Green A', 'Green');
        $this->join($green, $andi, '2026-07-01');

        $sarah = $this->groupAssignment($green, $this->english, $this->teacher('Sarah'));
        $this->score($andi, $this->assessment($sarah, 'Semester 1', '2026-11-01', 'Sarah test'), 70);
        $sarah->update(['ended_on' => '2026-12-15']);

        $eka = $this->groupAssignment($green, $this->english, $this->teacher('Eka'), '2026-12-16');
        $this->score($andi, $this->assessment($eka, 'Semester 2', '2027-02-01', 'Eka test'), 90);

        $rows = $this->rows($andi);
        $this->assertSame(1, $rows->where('subject.name', 'English')->count());

        $row = $this->rowFor($andi, 'English');
        $this->assertEquals(70, $row->periodAverages[$this->period('Semester 1')->id]);
        $this->assertEquals(90, $row->periodAverages[$this->period('Semester 2')->id]);
        $this->assertEquals(80, $row->overall, 'both teachers contribute');
    }

    // ---------------------------------------------------- completeness / no result

    public function test_membership_overlapping_the_year_adds_the_subject_with_empty_values(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);

        $green = $this->group('Green A', 'Green');
        $this->join($green, $andi, '2026-07-01');
        $this->groupAssignment($green, $this->english);

        $row = $this->rowFor($andi, 'English');

        $this->assertNull($row->overall);
        $this->assertNull($row->periodAverages[$this->period('Semester 1')->id]);
        $this->assertNull($row->periodAverages[$this->period('Semester 2')->id]);
    }

    public function test_a_closed_membership_within_the_year_still_adds_the_subject(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);

        $green = $this->group('Green A', 'Green');
        $this->join($green, $andi, '2026-07-01', '2026-12-15');
        $this->groupAssignment($green, $this->english);

        $this->assertContains('English', $this->rows($andi)->pluck('subject.name')->all(),
            'a Semester 1 membership still counts towards the annual report');
    }

    public function test_membership_in_another_academic_year_does_not_add_the_subject(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);

        $otherYear = AcademicYear::create([
            'name' => '2027/2028', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'is_current' => false,
        ]);
        $otherGroup = TeachingGroup::create([
            'academic_year_id' => $otherYear->id, 'name' => 'Green A (next year)',
            'english_level_id' => EnglishLevel::where('name', 'Green')->firstOrFail()->id, 'status' => 'active',
        ]);
        $otherGroup->memberships()->create(['student_id' => $andi->id, 'started_on' => '2027-08-01']);
        ClassSubject::create([
            'teaching_group_id' => $otherGroup->id, 'subject_id' => $this->english->id,
            'staff_id' => $this->teacher('Eka')->id, 'started_on' => '2027-08-01',
        ]);

        $this->assertNotContains('English', $this->rows($andi)->pluck('subject.name')->all());
    }

    // -------------------------------------------------------- year / period integrity

    public function test_an_assessment_from_another_academic_year_is_excluded(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);

        $green = $this->group('Green A', 'Green');
        $this->join($green, $andi, '2026-07-01');
        $assignment = $this->groupAssignment($green, $this->english);
        $this->score($andi, $this->assessment($assignment, 'Semester 1', '2026-11-01'), 85);

        // A result whose period belongs to a different year.
        $otherYear = AcademicYear::create([
            'name' => '2027/2028', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'is_current' => false,
        ]);
        $foreignPeriod = AcademicPeriod::create([
            'academic_year_id' => $otherYear->id, 'name' => 'Semester 1', 'sequence' => 1,
            'start_date' => '2027-07-01', 'end_date' => '2027-12-31',
        ]);
        $foreign = Assessment::create([
            'class_subject_id' => $assignment->id, 'academic_period_id' => $foreignPeriod->id,
            'name' => 'Next year', 'max_score' => 100, 'assessment_date' => '2027-09-01',
        ]);
        AssessmentResult::create(['assessment_id' => $foreign->id, 'student_id' => $andi->id, 'score' => 10]);

        $this->assertEquals(85, $this->rowFor($andi, 'English')->overall, 'next year must not drag the average down');
    }

    public function test_period_columns_follow_sequence_and_are_not_hardcoded(): void
    {
        $this->seedReferenceData();

        // A third period, out of sequence order on insert.
        AcademicPeriod::create([
            'academic_year_id' => $this->year->id, 'name' => 'Semester 3', 'sequence' => 3,
            'start_date' => '2027-05-01', 'end_date' => '2027-06-30',
        ]);

        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);
        $this->classAssignment($class, $this->maths);

        $periods = $this->viewData($andi, 'periods');

        $this->assertSame(['Semester 1', 'Semester 2', 'Semester 3'], $periods->pluck('name')->all());
        $this->assertSame([1, 2, 3], $periods->pluck('sequence')->all());
    }

    public function test_the_deprecated_term_column_is_not_read(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);
        $assignment = $this->classAssignment($class, $this->maths);

        $assessment = $this->assessment($assignment, 'Semester 1', '2026-11-01');
        $this->score($andi, $assessment, 85);

        // Deliberately misleading legacy value; the report must ignore it.
        \DB::table('assessments')->where('id', $assessment->id)->update(['term' => 'Term 3']);

        $row = $this->rowFor($andi, 'Mathematics');

        $this->assertEquals(85, $row->periodAverages[$this->period('Semester 1')->id]);
    }

    // ------------------------------------------------------------- score source

    public function test_proficiency_placement_does_not_affect_the_academic_score(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);

        $green = $this->group('Green A', 'Green');
        $this->join($green, $andi, '2026-07-01');
        $this->score($andi, $this->assessment($this->groupAssignment($green, $this->english), 'Semester 1', '2026-11-01'), 85);

        $before = $this->rowFor($andi, 'English')->overall;

        // Re-assessed proficiency Blue -> Red, with no new academic assessment.
        StudentEnglishLevelPlacement::create([
            'student_id' => $andi->id, 'english_level_id' => EnglishLevel::where('name', 'Blue')->firstOrFail()->id,
            'started_on' => '2026-07-01', 'ended_on' => '2026-12-15',
        ]);
        StudentEnglishLevelPlacement::create([
            'student_id' => $andi->id, 'english_level_id' => EnglishLevel::where('name', 'Red')->firstOrFail()->id,
            'started_on' => '2026-12-16',
        ]);

        $this->assertEquals($before, $this->rowFor($andi, 'English')->overall, 'proficiency is a separate fact from score');
        $this->assertEquals(85, $this->rowFor($andi, 'English')->overall);
    }

    public function test_the_report_card_reads_only_assessment_results(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);

        $green = $this->group('Green A', 'Green');
        $this->join($green, $andi, '2026-07-01');
        $assignment = $this->groupAssignment($green, $this->english);
        $assessment = $this->assessment($assignment, 'Semester 1', '2026-11-01');
        $this->score($andi, $assessment, 85);

        $this->assertEquals(85, $this->rowFor($andi, 'English')->overall);

        // Remove the only score row: the value must vanish, proving nothing
        // else is holding a copy of it.
        AssessmentResult::where('assessment_id', $assessment->id)->delete();

        $this->assertNull($this->rowFor($andi, 'English')->overall);
    }

    // ---------------------------------------------------------------- helpers

    private function rows(Student $student)
    {
        return $this->viewData($student, 'rows');
    }

    private function viewData(Student $student, string $key)
    {
        return Livewire::actingAs($this->admin())
            ->test(ReportCard::class, ['student' => $student])
            ->set('academic_year_id', (string) $this->year->id)
            ->viewData($key);
    }

    private function rowFor(Student $student, string $subject): object
    {
        $row = $this->rows($student)->firstWhere('subject.name', $subject);

        $this->assertNotNull($row, "expected a {$subject} row on the report card");

        return $row;
    }

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

    private function classAssignment(SchoolClass $class, Subject $subject, ?Staff $teacher = null, string $from = '2026-07-01'): ClassSubject
    {
        return ClassSubject::create([
            'class_id' => $class->id, 'subject_id' => $subject->id,
            'staff_id' => ($teacher ?? $this->teacher('Sarah'))->id, 'started_on' => $from,
        ]);
    }

    private function groupAssignment(TeachingGroup $group, Subject $subject, ?Staff $teacher = null, string $from = '2026-07-01'): ClassSubject
    {
        return ClassSubject::create([
            'teaching_group_id' => $group->id, 'subject_id' => $subject->id,
            'staff_id' => ($teacher ?? $this->teacher('Sarah'))->id, 'started_on' => $from,
        ]);
    }

    private function assessment(ClassSubject $assignment, string $period, string $date, string $name = 'Test', int $max = 100): Assessment
    {
        return Assessment::create([
            'class_subject_id' => $assignment->id,
            'academic_period_id' => $this->period($period)->id,
            'name' => $name, 'max_score' => $max, 'assessment_date' => $date,
        ]);
    }

    private function score(Student $student, Assessment $assessment, float $score): AssessmentResult
    {
        return AssessmentResult::create([
            'assessment_id' => $assessment->id, 'student_id' => $student->id, 'score' => $score,
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
}
