<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Grade;
use App\Models\Position;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_teacher_can_record_attendance_for_their_own_class(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$teacherUser, $teacherStaff, $class] = $this->makeTeacherWithClass();
        $student = $this->enrollStudent($class, 'STU-001', 'Andi');

        $this->actingAs($teacherUser);

        $attendance = Attendance::create([
            'class_id' => $class->id,
            'academic_year_id' => $class->academic_year_id,
            'date' => now()->toDateString(),
            'taken_by' => $teacherStaff->id,
        ]);
        $attendance->records()->create(['student_id' => $student->id, 'status' => 'present']);

        $this->assertDatabaseHas('attendance_records', [
            'attendance_id' => $attendance->id,
            'student_id' => $student->id,
            'status' => 'present',
        ]);
        $this->assertTrue($teacherUser->can('recordFor', [Attendance::class, $class]));
    }

    public function test_a_teacher_cannot_record_attendance_for_a_class_they_do_not_teach(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$teacherUser] = $this->makeTeacherWithClass();

        $ay = AcademicYear::current();
        $grade = Grade::first();
        $otherClass = SchoolClass::create(['name' => 'Other Class', 'grade_id' => $grade->id, 'academic_year_id' => $ay->id]);

        $this->assertFalse($teacherUser->can('recordFor', [Attendance::class, $otherClass]));
    }

    public function test_a_teacher_cannot_edit_attendance_from_a_previous_day(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$teacherUser, $teacherStaff, $class] = $this->makeTeacherWithClass();

        $yesterday = Attendance::create([
            'class_id' => $class->id,
            'academic_year_id' => $class->academic_year_id,
            'date' => now()->subDay()->toDateString(),
            'taken_by' => $teacherStaff->id,
        ]);

        $today = Attendance::create([
            'class_id' => $class->id,
            'academic_year_id' => $class->academic_year_id,
            'date' => now()->toDateString(),
            'taken_by' => $teacherStaff->id,
        ]);

        $this->assertFalse($teacherUser->can('update', $yesterday));
        $this->assertTrue($teacherUser->can('update', $today));
    }

    public function test_admin_staff_can_edit_attendance_from_a_previous_day(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [, $teacherStaff, $class] = $this->makeTeacherWithClass();

        $adminUser = User::factory()->create();
        $adminUser->assignRole('admin_staff');

        $yesterday = Attendance::create([
            'class_id' => $class->id,
            'academic_year_id' => $class->academic_year_id,
            'date' => now()->subDay()->toDateString(),
            'taken_by' => $teacherStaff->id,
        ]);

        $this->assertTrue($adminUser->can('update', $yesterday));
    }

    public function test_attendance_session_and_records_are_unique_per_class_per_day(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [, $teacherStaff, $class] = $this->makeTeacherWithClass();
        $student = $this->enrollStudent($class, 'STU-001', 'Andi');

        $attendance = Attendance::create([
            'class_id' => $class->id,
            'academic_year_id' => $class->academic_year_id,
            'date' => now()->toDateString(),
            'taken_by' => $teacherStaff->id,
        ]);
        $attendance->records()->create(['student_id' => $student->id, 'status' => 'present']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Attendance::create([
            'class_id' => $class->id,
            'academic_year_id' => $class->academic_year_id,
            'date' => now()->toDateString(),
            'taken_by' => $teacherStaff->id,
        ]);
    }

    public function test_a_students_attendance_history_survives_across_sessions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [, $teacherStaff, $class] = $this->makeTeacherWithClass();
        $student = $this->enrollStudent($class, 'STU-001', 'Andi');

        foreach ([now()->subDays(2), now()->subDay(), now()] as $date) {
            $attendance = Attendance::create([
                'class_id' => $class->id,
                'academic_year_id' => $class->academic_year_id,
                'date' => $date->toDateString(),
                'taken_by' => $teacherStaff->id,
            ]);
            $attendance->records()->create(['student_id' => $student->id, 'status' => 'present']);
        }

        $this->assertSame(3, $student->attendanceRecords()->count());
    }

    /**
     * @return array{0: User, 1: Staff, 2: SchoolClass}
     */
    private function makeTeacherWithClass(): array
    {
        $ay = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_current' => true,
        ]);
        $grade = Grade::create(['name' => 'Year 5', 'level_order' => 6]);
        $class = SchoolClass::create(['name' => 'Year 5A', 'grade_id' => $grade->id, 'academic_year_id' => $ay->id]);

        $position = Position::create(['title' => 'Homeroom Teacher']);
        $teacherUser = User::factory()->create();
        $teacherUser->assignRole('teacher');
        $teacherStaff = Staff::create([
            'staff_number' => 'STF-'.uniqid(),
            'first_name' => 'Budi',
            'last_name' => 'Santoso',
            'position_id' => $position->id,
            'phone' => '0812-0000-0001',
            'hire_date' => '2020-07-01',
            'user_id' => $teacherUser->id,
        ]);
        $class->teachers()->attach($teacherStaff->id, ['role' => 'homeroom']);

        return [$teacherUser, $teacherStaff, $class];
    }

    private function enrollStudent(SchoolClass $class, string $number, string $firstName): Student
    {
        $student = Student::create([
            'student_number' => $number,
            'first_name' => $firstName,
            'last_name' => 'Wijaya',
            'date_of_birth' => '2015-03-12',
            'gender' => 'male',
            'enrollment_date' => '2026-07-01',
        ]);
        $class->students()->attach($student->id, ['enrolled_at' => '2026-07-01', 'status' => 'active']);

        return $student;
    }
}
