<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\Position;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
