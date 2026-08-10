<?php

namespace Tests\Feature;

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
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_teacher_can_create_assessments_only_for_their_own_class_subject(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$teacherUser, , $classSubject] = $this->makeClassSubject();

        $otherTeacherUser = User::factory()->create();
        $otherTeacherUser->assignRole('teacher');

        $this->assertTrue($teacherUser->can('createFor', [Assessment::class, $classSubject]));
        $this->assertFalse($otherTeacherUser->can('createFor', [Assessment::class, $classSubject]));
    }

    public function test_a_teacher_cannot_record_scores_for_another_teachers_subject(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$teacherUser, $staff, $classSubject] = $this->makeClassSubject();

        $assessment = $classSubject->assessments()->create([
            'name' => 'Midterm', 'academic_period_id' => $this->periodFor($classSubject)->id, 'max_score' => 100, 'assessment_date' => now(),
        ]);

        $otherTeacherUser = User::factory()->create();
        $otherTeacherUser->assignRole('teacher');

        $this->assertTrue($teacherUser->can('recordScores', $assessment));
        $this->assertFalse($otherTeacherUser->can('recordScores', $assessment));
    }

    public function test_admin_staff_can_manage_subjects_but_teacher_cannot(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $adminUser = User::factory()->create();
        $adminUser->assignRole('admin_staff');
        $this->assertTrue($adminUser->can('create', Subject::class));

        $teacherUser = User::factory()->create();
        $teacherUser->assignRole('teacher');
        $this->assertFalse($teacherUser->can('create', Subject::class));
        $this->assertTrue($teacherUser->can('viewAny', Subject::class));
    }

    public function test_a_class_subject_with_assessments_cannot_be_deleted(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [, , $classSubject] = $this->makeClassSubject();

        $classSubject->assessments()->create([
            'name' => 'Midterm', 'academic_period_id' => $this->periodFor($classSubject)->id, 'max_score' => 100, 'assessment_date' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $classSubject->delete();
    }

    public function test_report_card_averages_scores_correctly_within_a_term(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [, $staff, $classSubject] = $this->makeClassSubject();
        $student = Student::create([
            'student_number' => 'STU-001', 'first_name' => 'Andi', 'last_name' => 'Wijaya',
            'date_of_birth' => '2015-03-12', 'gender' => 'male', 'enrollment_date' => '2026-07-01',
        ]);
        $classSubject->schoolClass->students()->attach($student->id, ['enrolled_at' => '2026-07-01', 'status' => 'active']);

        $a1 = $classSubject->assessments()->create(['name' => 'Quiz 1', 'academic_period_id' => $this->periodFor($classSubject)->id, 'max_score' => 50, 'assessment_date' => now()]);
        $a2 = $classSubject->assessments()->create(['name' => 'Quiz 2', 'academic_period_id' => $this->periodFor($classSubject)->id, 'max_score' => 100, 'assessment_date' => now()]);

        $a1->results()->create(['student_id' => $student->id, 'score' => 40]); // 80%
        $a2->results()->create(['student_id' => $student->id, 'score' => 90]); // 90%

        // (80 + 90) / 2 = 85%
        $results = $student->assessmentResults()->with('assessment')->get();
        $percentages = $results->map(fn ($r) => (float) $r->score / (float) $r->assessment->max_score * 100);

        $this->assertSame(85.0, round($percentages->avg(), 1));
    }

    /**
     * @return array{0: User, 1: Staff, 2: ClassSubject}
     */
    private function makeClassSubject(): array
    {
        $ay = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_current' => true,
        ]);
        $grade = Grade::create(['name' => 'Year 5', 'level_order' => 6]);
        $class = SchoolClass::create(['name' => 'Year 5A', 'grade_id' => $grade->id, 'academic_year_id' => $ay->id]);

        $position = Position::create(['title' => 'Homeroom Teacher']);
        $teacherUser = User::factory()->create();
        $teacherUser->assignRole('teacher');
        $staff = Staff::create([
            'staff_number' => 'STF-'.uniqid(), 'first_name' => 'Budi', 'last_name' => 'Santoso',
            'position_id' => $position->id, 'phone' => '0812-0000-0001', 'hire_date' => '2020-07-01',
            'user_id' => $teacherUser->id,
        ]);

        $subject = Subject::create(['name' => 'Mathematics', 'grade_id' => $grade->id]);
        $classSubject = ClassSubject::create(['class_id' => $class->id, 'subject_id' => $subject->id, 'staff_id' => $staff->id]);

        $this->seed(\Database\Seeders\AcademicPeriodSeeder::class);

        return [$teacherUser, $staff, $classSubject];
    }

    /**
     * First configured period of the assignment's academic year.
     */
    private function periodFor(ClassSubject $classSubject): \App\Models\AcademicPeriod
    {
        return $classSubject->schoolClass->academicYear->periods->first();
    }
}
