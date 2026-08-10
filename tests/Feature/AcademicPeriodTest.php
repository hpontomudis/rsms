<?php

namespace Tests\Feature;

use App\Livewire\Academics\ReportCard;
use App\Livewire\Assessments\Create as AssessmentCreate;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Models\Grade;
use App\Models\Position;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\AcademicPeriodSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step 1: academic-period canonicalisation.
 *
 * `academic_periods` replaces the hardcoded Term 1/2/3 vocabulary that Phase 4
 * baked into both the assessment form and the report card. The count and names
 * of periods are DATA -- these tests pin that a year with a different number of
 * periods needs no code change.
 */
class AcademicPeriodTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------ structure & constraints

    public function test_periods_belong_to_an_academic_year(): void
    {
        $year = $this->makeYear('2026/2027');
        $this->seed(AcademicPeriodSeeder::class);

        $period = $year->periods()->first();

        $this->assertSame($year->id, $period->academic_year_id);
        $this->assertSame($year->id, $period->academicYear->id);
    }

    public function test_an_academic_year_can_have_many_periods(): void
    {
        $year = $this->makeYear('2026/2027');
        $this->seed(AcademicPeriodSeeder::class);

        // Seeded with two; a third is purely a data addition.
        $this->assertCount(2, $year->periods()->get());

        AcademicPeriod::create([
            'academic_year_id' => $year->id, 'name' => 'Semester 3', 'sequence' => 3,
            'start_date' => '2027-01-01', 'end_date' => '2027-06-30',
        ]);

        $this->assertCount(3, $year->periods()->get());
    }

    public function test_duplicate_period_names_within_a_year_are_rejected(): void
    {
        $year = $this->makeYear('2026/2027');
        $this->seed(AcademicPeriodSeeder::class);

        $this->expectException(QueryException::class);

        AcademicPeriod::create([
            'academic_year_id' => $year->id, 'name' => 'Semester 1', 'sequence' => 99,
            'start_date' => '2026-07-01', 'end_date' => '2026-12-31',
        ]);
    }

    public function test_duplicate_sequences_within_a_year_are_rejected(): void
    {
        $year = $this->makeYear('2026/2027');
        $this->seed(AcademicPeriodSeeder::class);

        $this->expectException(QueryException::class);

        AcademicPeriod::create([
            'academic_year_id' => $year->id, 'name' => 'Something Else', 'sequence' => 1,
            'start_date' => '2026-07-01', 'end_date' => '2026-12-31',
        ]);
    }

    public function test_the_same_period_name_may_exist_in_different_academic_years(): void
    {
        $first = $this->makeYear('2026/2027');
        $second = $this->makeYear('2027/2028', current: false, start: '2027-07-01', end: '2028-06-30');

        $this->seed(AcademicPeriodSeeder::class);

        $this->assertSame('Semester 1', $first->periods()->first()->name);
        $this->assertSame('Semester 1', $second->periods()->first()->name);
        $this->assertNotSame($first->periods()->first()->id, $second->periods()->first()->id);
    }

    public function test_a_period_cannot_be_deleted_while_assessments_reference_it(): void
    {
        [$classSubject] = $this->makeTeachingContext();
        $period = $classSubject->schoolClass->academicYear->periods->first();

        $classSubject->assessments()->create([
            'name' => 'Midterm', 'academic_period_id' => $period->id,
            'max_score' => 100, 'assessment_date' => now(),
        ]);

        $this->expectException(QueryException::class);
        $period->delete();
    }

    // -------------------------------------------------------- assessment path

    public function test_creating_an_assessment_stores_an_academic_period_and_never_a_term(): void
    {
        [$classSubject, $adminUser] = $this->makeTeachingContext();
        $period = $classSubject->schoolClass->academicYear->periods->last();

        Livewire::actingAs($adminUser)
            ->test(AssessmentCreate::class, ['class_subject_id' => (string) $classSubject->id])
            ->set('name', 'Final Exam')
            ->set('academic_period_id', (string) $period->id)
            ->set('max_score', '100')
            ->set('assessment_date', now()->toDateString())
            ->call('save');

        $assessment = Assessment::where('name', 'Final Exam')->firstOrFail();

        $this->assertSame($period->id, $assessment->academic_period_id);
        $this->assertNull(
            DB::table('assessments')->where('id', $assessment->id)->value('term'),
            'the deprecated term column must never be written to'
        );
    }

    public function test_editing_an_assessment_moves_it_between_periods(): void
    {
        [$classSubject] = $this->makeTeachingContext();
        $periods = $classSubject->schoolClass->academicYear->periods;

        $assessment = $classSubject->assessments()->create([
            'name' => 'Midterm', 'academic_period_id' => $periods->first()->id,
            'max_score' => 100, 'assessment_date' => now(),
        ]);

        $assessment->update(['academic_period_id' => $periods->last()->id]);

        $this->assertSame($periods->last()->id, $assessment->fresh()->academic_period_id);
    }

    public function test_an_assessment_cannot_reference_a_period_from_another_academic_year(): void
    {
        [$classSubject, $adminUser] = $this->makeTeachingContext();

        $otherYear = $this->makeYear('2027/2028', current: false, start: '2027-07-01', end: '2028-06-30');
        $foreignPeriod = AcademicPeriod::create([
            'academic_year_id' => $otherYear->id, 'name' => 'Semester 1', 'sequence' => 1,
            'start_date' => '2027-07-01', 'end_date' => '2027-12-31',
        ]);

        Livewire::actingAs($adminUser)
            ->test(AssessmentCreate::class, ['class_subject_id' => (string) $classSubject->id])
            ->set('name', 'Wrong Year Exam')
            ->set('academic_period_id', (string) $foreignPeriod->id)
            ->set('max_score', '100')
            ->set('assessment_date', now()->toDateString())
            ->call('save')
            ->assertHasErrors('academic_period_id');

        $this->assertDatabaseMissing('assessments', ['name' => 'Wrong Year Exam']);
    }

    // ------------------------------------------------------------- reportcard

    public function test_report_card_columns_follow_the_configured_period_sequence(): void
    {
        [$classSubject, $adminUser, $student] = $this->makeTeachingContext();

        $periods = Livewire::actingAs($adminUser)
            ->test(ReportCard::class, ['student' => $student])
            ->viewData('periods');

        $this->assertSame(['Semester 1', 'Semester 2'], $periods->pluck('name')->all());
        $this->assertSame([1, 2], $periods->pluck('sequence')->all());
    }

    public function test_report_card_groups_results_by_academic_period(): void
    {
        [$classSubject, $adminUser, $student] = $this->makeTeachingContext();
        $periods = $classSubject->schoolClass->academicYear->periods;

        // 85% in Semester 1 -- the same figure the pre-migration test scenario
        // produced, proving the refactor preserved the calculation.
        $sem1 = $classSubject->assessments()->create([
            'name' => 'Midterm Test', 'academic_period_id' => $periods->first()->id,
            'max_score' => 100, 'assessment_date' => now(),
        ]);
        $sem1->results()->create(['student_id' => $student->id, 'score' => 85]);

        $sem2 = $classSubject->assessments()->create([
            'name' => 'Final Exam', 'academic_period_id' => $periods->last()->id,
            'max_score' => 100, 'assessment_date' => now(),
        ]);
        $sem2->results()->create(['student_id' => $student->id, 'score' => 95]);

        $rows = Livewire::actingAs($adminUser)
            ->test(ReportCard::class, ['student' => $student])
            ->viewData('rows');

        $maths = $rows->firstWhere(fn ($r) => $r->subject->name === 'Mathematics');

        $this->assertEquals(85, $maths->periodAverages[$periods->first()->id]);
        $this->assertEquals(95, $maths->periodAverages[$periods->last()->id]);
        $this->assertEquals(90, $maths->overall);
    }

    public function test_a_third_period_needs_only_data_no_code_change(): void
    {
        [$classSubject, $adminUser, $student] = $this->makeTeachingContext();
        $year = $classSubject->schoolClass->academicYear;

        AcademicPeriod::create([
            'academic_year_id' => $year->id, 'name' => 'Semester 3', 'sequence' => 3,
            'start_date' => '2027-04-01', 'end_date' => '2027-06-30',
        ]);

        $third = $year->periods()->where('sequence', 3)->first();
        $assessment = $classSubject->assessments()->create([
            'name' => 'Third Period Test', 'academic_period_id' => $third->id,
            'max_score' => 100, 'assessment_date' => now(),
        ]);
        $assessment->results()->create(['student_id' => $student->id, 'score' => 70]);

        $component = Livewire::actingAs($adminUser)->test(ReportCard::class, ['student' => $student]);

        // Three columns render, and the third one carries its score -- with no
        // application code aware that "three" is now the number.
        $this->assertSame(['Semester 1', 'Semester 2', 'Semester 3'], $component->viewData('periods')->pluck('name')->all());

        $maths = $component->viewData('rows')->firstWhere(fn ($r) => $r->subject->name === 'Mathematics');
        $this->assertEquals(70, $maths->periodAverages[$third->id]);
    }

    // ---------------------------------------------------------------- helpers

    private function makeYear(string $name, bool $current = true, string $start = '2026-07-01', string $end = '2027-06-30'): AcademicYear
    {
        return AcademicYear::create([
            'name' => $name, 'start_date' => $start, 'end_date' => $end, 'is_current' => $current,
        ]);
    }

    /**
     * @return array{0: ClassSubject, 1: User, 2: Student}
     */
    private function makeTeachingContext(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $year = $this->makeYear('2026/2027');
        $grade = Grade::create(['name' => 'Year 5', 'level_order' => 6]);
        $class = SchoolClass::create(['name' => 'Year 5A', 'grade_id' => $grade->id, 'academic_year_id' => $year->id]);
        $subject = Subject::create(['name' => 'Mathematics', 'grade_id' => $grade->id]);
        $position = Position::create(['title' => 'Subject Teacher']);

        $staff = Staff::create([
            'staff_number' => 'STF-'.uniqid(), 'first_name' => 'Budi', 'last_name' => 'Santoso',
            'position_id' => $position->id, 'phone' => '0812-0000-0001', 'hire_date' => '2020-07-01',
        ]);

        $classSubject = ClassSubject::create([
            'class_id' => $class->id, 'subject_id' => $subject->id, 'staff_id' => $staff->id,
            'started_on' => '2026-07-01',
        ]);

        $student = Student::create([
            'student_number' => 'STU-001', 'first_name' => 'Andi', 'last_name' => 'Wijaya',
            'date_of_birth' => '2015-03-12', 'gender' => 'male', 'enrollment_date' => '2026-07-01',
        ]);
        $class->students()->attach($student->id, ['enrolled_at' => '2026-07-01', 'status' => 'active']);

        $this->seed(AcademicPeriodSeeder::class);

        $adminUser = User::factory()->create();
        $adminUser->assignRole('admin_staff');

        return [$classSubject->fresh(), $adminUser, $student];
    }
}
