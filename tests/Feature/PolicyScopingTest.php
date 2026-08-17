<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\ClassTeacher;
use App\Models\Grade;
use App\Models\Position;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\ClassStudentService;
use App\Services\ClassTeacherService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PolicyScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_teacher_can_only_view_students_in_their_own_class(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $ay = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_current' => true]);
        $grade = Grade::create(['name' => 'Year 5', 'level_order' => 6]);

        $classA = SchoolClass::create(['name' => 'Year 5A', 'grade_id' => $grade->id, 'academic_year_id' => $ay->id]);
        $classB = SchoolClass::create(['name' => 'Year 5B', 'grade_id' => $grade->id, 'academic_year_id' => $ay->id]);

        $position = Position::create(['title' => 'Homeroom Teacher']);
        $teacherUser = User::factory()->create();
        $teacherUser->assignRole('teacher');
        $teacherStaff = Staff::create([
            'staff_number' => 'STF-001', 'first_name' => 'Budi', 'last_name' => 'Santoso',
            'position_id' => $position->id, 'phone' => '0812-0000-0001', 'hire_date' => '2020-07-01',
            'user_id' => $teacherUser->id,
        ]);
        $classA->teachers()->attach($teacherStaff->id, ['role' => 'homeroom']);

        $ownStudent = Student::create([
            'student_number' => 'STU-001', 'first_name' => 'Andi', 'last_name' => 'Wijaya',
            'date_of_birth' => '2015-03-12', 'gender' => 'male', 'enrollment_date' => '2026-07-01',
        ]);
        $classA->students()->attach($ownStudent->id, ['enrolled_at' => '2026-07-01', 'status' => 'active']);

        $otherStudent = Student::create([
            'student_number' => 'STU-002', 'first_name' => 'Citra', 'last_name' => 'Putri',
            'date_of_birth' => '2015-04-01', 'gender' => 'female', 'enrollment_date' => '2026-07-01',
        ]);
        $classB->students()->attach($otherStudent->id, ['enrolled_at' => '2026-07-01', 'status' => 'active']);

        $this->assertTrue($teacherUser->can('view', $ownStudent));
        $this->assertFalse($teacherUser->can('view', $otherStudent));

        $this->assertTrue($teacherUser->can('view', $classA));
        $this->assertFalse($teacherUser->can('view', $classB));

        // admin_staff is not scoped to specific classes
        $adminUser = User::factory()->create();
        $adminUser->assignRole('admin_staff');
        $this->assertTrue($adminUser->can('view', $ownStudent));
        $this->assertTrue($adminUser->can('view', $otherStudent));

        // teacher has no create/update permission at all
        $this->assertFalse($teacherUser->can('create', Student::class));
        $this->assertTrue($adminUser->can('create', Student::class));
    }

    /**
     * Foundation F3.1 -- LOAD-BEARING. StudentPolicy::teaches() previously
     * queried $student->classes() and the class_teacher pivot with no
     * effective-dating filter at all, so a transferred-out Student's OLD
     * class -- and a teacher's own CLOSED class_teacher row -- could both
     * still grant "currently teaches this student" access. Historical
     * class enrollment must not grant current teacher authority.
     */
    public function test_a_transfer_moves_student_access_from_the_outgoing_to_the_incoming_teacher(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$year, $grade, $classA, $classB] = $this->twoClasses();
        $teacherA = $this->teacherWithClass($classA, 'Sarah', 'STF-A');
        $teacherB = $this->teacherWithClass($classB, 'Eka', 'STF-B');
        $student = $this->studentIn($classA, 'STU-A1');

        $this->assertTrue($teacherA->user->can('view', $student), 'A: teacher currently authorized for the student\'s current class');
        $this->assertFalse($teacherB->user->can('view', $student), 'unrelated teacher denied before transfer');

        app(ClassStudentService::class)->transfer($student, $classB, Carbon::parse('2026-08-15'));

        $this->assertFalse(
            $teacherA->user->fresh()->can('view', $student->fresh()),
            'outgoing teacher must NOT retain access from the now-historical Class A enrollment'
        );
        $this->assertTrue(
            $teacherB->user->fresh()->can('view', $student->fresh()),
            'incoming teacher gains access via the new current enrollment'
        );

        $this->assertDatabaseHas('class_student', ['student_id' => $student->id, 'class_id' => $classA->id, 'status' => 'transferred_out']);
    }

    public function test_a_withdrawn_students_historical_enrollment_does_not_grant_teacher_access(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$year, $grade, $classA] = $this->twoClasses();
        $teacherA = $this->teacherWithClass($classA, 'Sarah', 'STF-A');
        $student = $this->studentIn($classA, 'STU-W1');

        $this->assertTrue($teacherA->user->can('view', $student));

        app(ClassStudentService::class)->withdraw($student, Carbon::parse('2026-08-15'));

        $this->assertFalse(
            $teacherA->user->fresh()->can('view', $student->fresh()),
            'a withdrawn (historical) enrollment must not grant current teacher access'
        );
        $this->assertDatabaseHas('class_student', ['student_id' => $student->id, 'class_id' => $classA->id, 'status' => 'withdrawn']);
    }

    public function test_a_closed_class_teacher_row_does_not_grant_access_even_for_a_currently_enrolled_student(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$year, $grade, $classA] = $this->twoClasses();
        $outgoing = $this->teacherWithClass($classA, 'Sarah', 'STF-A');
        $incoming = Staff::create([
            'staff_number' => 'STF-C', 'first_name' => 'Citra', 'last_name' => 'Teacher',
            'position_id' => Position::first()->id, 'phone' => '0812-0000-0009', 'hire_date' => '2020-07-01',
            'user_id' => tap(User::factory()->create(), fn ($u) => $u->assignRole('teacher'))->id,
        ]);
        $student = $this->studentIn($classA, 'STU-C1');

        // Handover the homeroom row; the student's own enrollment never moves.
        app(ClassTeacherService::class)->setHomeroom($classA, $incoming);

        $this->assertFalse(
            $outgoing->user->fresh()->can('view', $student->fresh()),
            'a closed class_teacher row must not grant access even though the student is still currently in the class'
        );
        $this->assertTrue($incoming->user->fresh()->can('view', $student->fresh()));
    }

    /**
     * Confirms the deliberate, documented boundary: StudentPolicy::teaches()
     * is homeroom/assistant class_teacher only. A subject-only teacher
     * (ClassSubject, no class_teacher row) is denied regardless of whether
     * the subject assignment is open or closed -- the exclusion is by
     * design (AcademicRecordPolicy/CommunicationPolicy docblocks), not an
     * oversight this patch should widen.
     */
    public function test_a_subject_only_teacher_via_class_subject_is_not_granted_student_policy_access(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$year, $grade, $classA] = $this->twoClasses();
        $student = $this->studentIn($classA, 'STU-S1');
        $subjectTeacherStaff = Staff::create([
            'staff_number' => 'STF-SUB', 'first_name' => 'Maya', 'last_name' => 'Subject',
            'position_id' => Position::firstOrCreate(['title' => 'Homeroom Teacher'])->id, 'phone' => '0812-0000-0008', 'hire_date' => '2020-07-01',
            'user_id' => tap(User::factory()->create(), fn ($u) => $u->assignRole('teacher'))->id,
        ]);
        $subject = Subject::firstOrCreate(['name' => 'Maths']);
        ClassSubject::create([
            'class_id' => $classA->id, 'subject_id' => $subject->id,
            'staff_id' => $subjectTeacherStaff->id, 'started_on' => '2026-07-01',
        ]);

        $this->assertFalse($subjectTeacherStaff->user->can('view', $student), 'open ClassSubject assignment alone does not grant StudentPolicy access');
    }

    public function test_admin_and_management_student_access_is_unaffected_by_the_effective_dating_fix(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$year, $grade, $classA] = $this->twoClasses();
        $student = $this->studentIn($classA, 'STU-M1');

        $adminUser = User::factory()->create();
        $adminUser->assignRole('admin_staff');
        $managementUser = User::factory()->create();
        $managementUser->assignRole('management');

        $this->assertTrue($adminUser->can('view', $student));
        $this->assertTrue($managementUser->can('view', $student));
    }

    /** @return array{0: AcademicYear, 1: Grade, 2: SchoolClass, 3: SchoolClass} */
    private function twoClasses(): array
    {
        $year = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_current' => true],
        );
        $grade = Grade::firstOrCreate(['name' => 'Year 5'], ['level_order' => 6]);
        $classA = SchoolClass::create(['name' => 'Year 5A-'.uniqid(), 'grade_id' => $grade->id, 'academic_year_id' => $year->id]);
        $classB = SchoolClass::create(['name' => 'Year 5B-'.uniqid(), 'grade_id' => $grade->id, 'academic_year_id' => $year->id]);

        return [$year, $grade, $classA, $classB];
    }

    private function teacherWithClass(SchoolClass $class, string $firstName, string $staffNumber): Staff
    {
        $user = User::factory()->create();
        $user->assignRole('teacher');
        $staff = Staff::create([
            'staff_number' => $staffNumber, 'first_name' => $firstName, 'last_name' => 'Teacher',
            'position_id' => Position::firstOrCreate(['title' => 'Homeroom Teacher'])->id,
            'phone' => '0812-0000-0001', 'hire_date' => '2020-07-01', 'user_id' => $user->id,
        ]);
        app(ClassTeacherService::class)->setHomeroom($class, $staff);

        return $staff;
    }

    private function studentIn(SchoolClass $class, string $number): Student
    {
        $student = Student::create([
            'student_number' => $number, 'first_name' => $number, 'last_name' => 'Test',
            'date_of_birth' => '2015-03-12', 'gender' => 'male', 'enrollment_date' => '2026-07-01',
        ]);
        app(ClassStudentService::class)->enroll($class, $student, Carbon::parse('2026-07-01'));

        return $student;
    }

    public function test_super_admin_bypasses_all_permission_checks(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $student = Student::create([
            'student_number' => 'STU-003', 'first_name' => 'Dedi', 'last_name' => 'Kurnia',
            'date_of_birth' => '2015-06-01', 'gender' => 'male', 'enrollment_date' => '2026-07-01',
        ]);

        $this->assertTrue($superAdmin->can('view', $student));
        $this->assertTrue($superAdmin->can('delete', $student));
    }
}
