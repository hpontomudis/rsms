<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Grade;
use App\Models\Guardian;
use App\Models\Position;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FoundationModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_and_permission_assignment(): void
    {
        $role = Role::create(['name' => 'teacher', 'guard_name' => 'web']);
        Permission::create(['name' => 'students.view', 'guard_name' => 'web']);
        $role->givePermissionTo('students.view');

        $user = User::factory()->create();
        $user->assignRole('teacher');

        $this->assertTrue($user->hasPermissionTo('students.view'));
    }

    public function test_one_guardian_can_have_multiple_children_without_duplicate_records(): void
    {
        $father = Guardian::create(['first_name' => 'Rudi', 'last_name' => 'Wijaya', 'phone' => '0812-0000-0002']);

        $child1 = Student::create($this->studentData('STU-001', 'Andi'));
        $child2 = Student::create($this->studentData('STU-002', 'Sinta'));

        $child1->guardians()->attach($father->id, ['relationship_type' => 'father', 'is_primary_contact' => true]);
        $child2->guardians()->attach($father->id, ['relationship_type' => 'father', 'is_primary_contact' => true]);

        $this->assertSame(1, Guardian::count());
        $this->assertSame(2, $father->students()->count());
    }

    public function test_a_student_can_have_multiple_guardians_with_a_primary_contact(): void
    {
        $student = Student::create($this->studentData('STU-003', 'Budi'));
        $father = Guardian::create(['first_name' => 'Rudi', 'last_name' => 'Wijaya', 'phone' => '0812-0000-0002']);
        $mother = Guardian::create(['first_name' => 'Sri', 'last_name' => 'Wijaya', 'phone' => '0812-0000-0003']);

        $student->guardians()->attach($father->id, ['relationship_type' => 'father', 'is_primary_contact' => true]);
        $student->guardians()->attach($mother->id, ['relationship_type' => 'mother', 'is_primary_contact' => false]);

        $this->assertSame(2, $student->guardians()->count());
        $this->assertTrue($student->primaryGuardian()->is($father));
    }

    public function test_a_class_has_a_homeroom_teacher_and_a_roster(): void
    {
        [$class, $staff] = $this->makeClassWithTeacher();
        $student = Student::create($this->studentData('STU-004', 'Citra'));
        $class->students()->attach($student->id, ['enrolled_at' => '2026-07-01', 'status' => 'active']);

        $this->assertTrue($class->homeroomTeacher()->is($staff));
        $this->assertSame(1, $class->students()->count());
        $this->assertTrue($student->currentClass()->is($class));
    }

    public function test_updating_a_student_writes_an_audit_log_entry(): void
    {
        $student = Student::create($this->studentData('STU-005', 'Dedi'));

        $student->update(['status' => 'graduated']);

        $log = AuditLog::where('auditable_type', Student::class)
            ->where('auditable_id', $student->id)
            ->where('action', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('graduated', $log->new_values['status']);
    }

    public function test_soft_deleting_a_student_hides_it_but_preserves_history(): void
    {
        [$class] = $this->makeClassWithTeacher();
        $student = Student::create($this->studentData('STU-006', 'Eka'));
        $class->students()->attach($student->id, ['enrolled_at' => '2026-07-01', 'status' => 'active']);

        $student->delete();

        $this->assertNull(Student::find($student->id));
        $this->assertNotNull(Student::withTrashed()->find($student->id));
        $this->assertSame(0, $class->students()->count());
    }

    private function studentData(string $number, string $firstName): array
    {
        return [
            'student_number' => $number,
            'first_name' => $firstName,
            'last_name' => 'Wijaya',
            'date_of_birth' => '2015-03-12',
            'gender' => 'male',
            'enrollment_date' => '2026-07-01',
        ];
    }

    private function makeClassWithTeacher(): array
    {
        $ay = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_current' => true,
        ]);
        $grade = Grade::create(['name' => 'Year 5', 'level_order' => 6]);
        $class = SchoolClass::create(['name' => 'Year 5A', 'grade_id' => $grade->id, 'academic_year_id' => $ay->id]);

        $position = Position::create(['title' => 'Homeroom Teacher']);
        $staff = Staff::create([
            'staff_number' => 'STF-'.uniqid(),
            'first_name' => 'Budi',
            'last_name' => 'Santoso',
            'position_id' => $position->id,
            'phone' => '0812-0000-0001',
            'hire_date' => '2020-07-01',
        ]);
        $class->teachers()->attach($staff->id, ['role' => 'homeroom']);

        return [$class, $staff];
    }
}
