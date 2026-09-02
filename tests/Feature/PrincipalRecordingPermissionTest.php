<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Grade;
use App\Models\Position;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the deliberate grant of `academics.record` to the principal role.
 *
 * Originally withheld: recording was framed as a teacher act and managing as
 * the principal's. It was granted because a principal who personally teaches
 * a class had no way to enter that class's scores at all.
 *
 * The scope this carries is wider than "their own class", and that is
 * accepted rather than accidental: AssessmentPolicy::hasClassSubjectAccess()
 * and DailyJournalPolicy::owns() both short-circuit to true for anyone who is
 * not hasRole('teacher'), so a principal records school-wide. These tests
 * state that plainly so the breadth can never be mistaken for an oversight.
 */
class PrincipalRecordingPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function principal(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('principal');

        return $user;
    }

    private function classSubject(): ClassSubject
    {
        $year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-07-01',
            'end_date' => '2027-06-30', 'is_current' => true,
        ]);
        $grade = Grade::create(['name' => 'Year 5', 'level_order' => 6]);
        $class = SchoolClass::create([
            'name' => 'Year 5A', 'grade_id' => $grade->id, 'academic_year_id' => $year->id,
        ]);
        $subject = Subject::create(['name' => 'Mathematics', 'grade_id' => $grade->id]);
        $staff = Staff::create([
            'staff_number' => 'PRN-REC-1', 'first_name' => 'Other', 'last_name' => 'Teacher',
            'position_id' => Position::firstOrCreate(['title' => 'Teacher'])->id,
            'phone' => '0812-0000-0000', 'hire_date' => '2020-07-01',
        ]);

        return ClassSubject::create([
            'class_id' => $class->id, 'subject_id' => $subject->id,
            'staff_id' => $staff->id, 'started_on' => '2026-07-01',
        ]);
    }

    public function test_principal_holds_academics_record(): void
    {
        $this->assertTrue($this->principal()->can('academics.record'));
    }

    public function test_principal_may_create_an_assessment_for_an_active_class_subject(): void
    {
        // The reason the grant exists: a principal who teaches can now record.
        $this->assertTrue(
            $this->principal()->can('createFor', [\App\Models\Assessment::class, $this->classSubject()])
        );
    }

    public function test_principal_records_school_wide_not_only_their_own_assignment(): void
    {
        // Deliberate and accepted: the class-subject below is assigned to a
        // DIFFERENT staff member, and the principal may still record on it,
        // because the "own assignment only" narrowing applies to teachers.
        $assignment = $this->classSubject();
        $principal = $this->principal();

        $this->assertNotNull($assignment->staff_id);
        $this->assertNull($principal->staff);
        $this->assertTrue($principal->can('createFor', [\App\Models\Assessment::class, $assignment]));
    }

    public function test_the_grant_does_not_give_principal_any_staff_administration_power(): void
    {
        // Guards against scope creep from this change: recording is academic,
        // not administrative. Account provisioning/reset stays untouched.
        $principal = $this->principal();

        $this->assertFalse($principal->can('users.reset-password'));
        $this->assertFalse($principal->can('staff.import'));
        $this->assertFalse($principal->can('staff.create'));
    }

    public function test_teacher_scoping_is_unchanged_by_this_grant(): void
    {
        // A teacher still may not record on someone else's assignment.
        $this->seed(RolesAndPermissionsSeeder::class);
        $assignment = $this->classSubject();

        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $this->assertTrue($teacher->can('academics.record'));
        $this->assertFalse($teacher->can('createFor', [\App\Models\Assessment::class, $assignment]));
    }
}
