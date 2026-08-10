<?php

namespace Tests\Feature;

use App\Livewire\TeachingGroups\Show as GroupShow;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\ClassSubject;
use App\Models\Grade;
use App\Models\Position;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingGroup;
use App\Models\User;
use App\Services\TeachingAssignmentService;
use Database\Seeders\EnglishProgrammeSeeder;
use Database\Seeders\GradeSeeder;
use Database\Seeders\PositionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step 2b: class_subject extended so a teaching assignment may be backed by
 * an administrative class OR a teaching group -- exactly one of the two.
 *
 * The table and model keep their old names; conceptually a row is a Teaching
 * Assignment. Nothing here touches assessments, report cards, or StudentPolicy.
 */
class TeachingAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year;

    private Subject $english;

    // ------------------------------------------------------------- structure

    public function test_an_existing_class_backed_assignment_is_still_valid(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');

        $assignment = ClassSubject::create([
            'class_id' => $class->id,
            'subject_id' => $this->english->id,
            'staff_id' => $this->teacher('Sarah')->id,
            'started_on' => '2026-07-01',
        ]);

        $this->assertTrue($assignment->isClassBacked());
        $this->assertFalse($assignment->isTeachingGroupBacked());
        $this->assertNull($assignment->teaching_group_id);
        $this->assertSame('Year 5A', $assignment->schoolClass->name);
    }

    public function test_a_group_backed_assignment_can_be_created(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A');

        $assignment = ClassSubject::create([
            'teaching_group_id' => $group->id,
            'subject_id' => $this->english->id,
            'staff_id' => $this->teacher('Sarah')->id,
            'started_on' => '2026-07-01',
        ]);

        $this->assertTrue($assignment->isTeachingGroupBacked());
        $this->assertFalse($assignment->isClassBacked());
        $this->assertNull($assignment->class_id);
        $this->assertSame('Green A', $assignment->teachingGroup->name);
    }

    public function test_an_assignment_with_neither_roster_source_is_rejected(): void
    {
        $this->seedReferenceData();

        $this->expectException(QueryException::class);

        ClassSubject::create([
            'class_id' => null,
            'teaching_group_id' => null,
            'subject_id' => $this->english->id,
            'staff_id' => $this->teacher('Sarah')->id,
            'started_on' => '2026-07-01',
        ]);
    }

    public function test_an_assignment_with_both_roster_sources_is_rejected(): void
    {
        $this->seedReferenceData();

        $this->expectException(QueryException::class);

        ClassSubject::create([
            'class_id' => $this->schoolClass('Year 5', 'Year 5A')->id,
            'teaching_group_id' => $this->group('Green A')->id,
            'subject_id' => $this->english->id,
            'staff_id' => $this->teacher('Sarah')->id,
            'started_on' => '2026-07-01',
        ]);
    }

    public function test_a_teaching_group_referenced_by_an_assignment_cannot_be_deleted(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A');

        ClassSubject::create([
            'teaching_group_id' => $group->id,
            'subject_id' => $this->english->id,
            'staff_id' => $this->teacher('Sarah')->id,
            'started_on' => '2026-07-01',
        ]);

        $this->expectException(QueryException::class);
        $group->delete();
    }

    // ------------------------------------------------------ active uniqueness

    public function test_only_one_active_assignment_per_class_and_subject(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');

        ClassSubject::create([
            'class_id' => $class->id, 'subject_id' => $this->english->id,
            'staff_id' => $this->teacher('Sarah')->id, 'started_on' => '2026-07-01',
        ]);

        $this->expectException(QueryException::class);

        ClassSubject::create([
            'class_id' => $class->id, 'subject_id' => $this->english->id,
            'staff_id' => $this->teacher('Eka')->id, 'started_on' => '2026-08-01',
        ]);
    }

    public function test_only_one_active_assignment_per_teaching_group_and_subject(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A');

        ClassSubject::create([
            'teaching_group_id' => $group->id, 'subject_id' => $this->english->id,
            'staff_id' => $this->teacher('Sarah')->id, 'started_on' => '2026-07-01',
        ]);

        $this->expectException(QueryException::class);

        ClassSubject::create([
            'teaching_group_id' => $group->id, 'subject_id' => $this->english->id,
            'staff_id' => $this->teacher('Eka')->id, 'started_on' => '2026-08-01',
        ]);
    }

    public function test_the_same_subject_may_be_active_in_two_different_groups(): void
    {
        $this->seedReferenceData();

        ClassSubject::create([
            'teaching_group_id' => $this->group('Green A')->id, 'subject_id' => $this->english->id,
            'staff_id' => $this->teacher('Sarah')->id, 'started_on' => '2026-07-01',
        ]);
        ClassSubject::create([
            'teaching_group_id' => $this->group('Blue A')->id, 'subject_id' => $this->english->id,
            'staff_id' => $this->teacher('Eka')->id, 'started_on' => '2026-07-01',
        ]);

        $this->assertSame(2, ClassSubject::active()->teachingGroupBacked()->count());
    }

    public function test_a_closed_group_assignment_allows_a_new_active_replacement(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A');

        ClassSubject::create([
            'teaching_group_id' => $group->id, 'subject_id' => $this->english->id,
            'staff_id' => $this->teacher('Sarah')->id,
            'started_on' => '2026-07-01', 'ended_on' => '2026-12-15',
        ]);

        $replacement = ClassSubject::create([
            'teaching_group_id' => $group->id, 'subject_id' => $this->english->id,
            'staff_id' => $this->teacher('Eka')->id, 'started_on' => '2026-12-16',
        ]);

        $this->assertTrue($replacement->isActive());
        $this->assertSame(2, ClassSubject::where('teaching_group_id', $group->id)->count());
    }

    // -------------------------------------------------------- reassignment

    public function test_reassigning_a_group_subject_closes_the_old_row_and_opens_a_new_one(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A');
        $sarah = $this->teacher('Sarah');
        $eka = $this->teacher('Eka');

        $original = $this->assignments()->assignToGroup($group, $this->english, $sarah, $this->date('2026-07-01'));
        $replacement = $this->assignments()->assignToGroup($group, $this->english, $eka, $this->date('2026-12-16'));

        $original->refresh();

        $this->assertSame('2026-12-15', $original->ended_on->toDateString(), 'old row closes the day before');
        $this->assertSame($sarah->id, $original->staff_id, 'old row keeps its original teacher');
        $this->assertSame($eka->id, $replacement->staff_id);
        $this->assertTrue($replacement->isActive());
        $this->assertNotSame($original->id, $replacement->id);
    }

    public function test_reassigning_to_the_teacher_already_in_place_is_a_no_op(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A');
        $sarah = $this->teacher('Sarah');

        $original = $this->assignments()->assignToGroup($group, $this->english, $sarah, $this->date('2026-07-01'));
        $again = $this->assignments()->assignToGroup($group, $this->english, $sarah, $this->date('2026-12-16'));

        $this->assertSame($original->id, $again->id);
        $this->assertTrue($again->isActive());
        $this->assertSame(1, ClassSubject::where('teaching_group_id', $group->id)->count(), 'no fake succession');
    }

    public function test_a_historical_group_assignment_survives_reassignment_intact(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A');
        $sarah = $this->teacher('Sarah');

        $original = $this->assignments()->assignToGroup($group, $this->english, $sarah, $this->date('2026-07-01'));
        $this->assignments()->assignToGroup($group, $this->english, $this->teacher('Eka'), $this->date('2026-12-16'));

        $original->refresh();

        $this->assertSame($group->id, $original->teaching_group_id);
        $this->assertSame($sarah->id, $original->staff_id);
        $this->assertSame('2026-07-01', $original->started_on->toDateString());
    }

    // ----------------------------------------------------------- validity

    public function test_an_archived_group_cannot_receive_a_new_assignment(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A');
        $group->update(['status' => 'archived']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('archived');

        $this->assignments()->assignToGroup($group, $this->english, $this->teacher('Sarah'), $this->date('2026-07-01'));
    }

    public function test_an_existing_assignment_stays_valid_when_its_group_is_later_archived(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A');

        $assignment = $this->assignments()->assignToGroup($group, $this->english, $this->teacher('Sarah'), $this->date('2026-07-01'));
        $group->update(['status' => 'archived']);

        $assignment->refresh();

        $this->assertTrue($assignment->isActive(), 'archival must not cascade into assignment history');
        $this->assertDatabaseHas('class_subject', ['id' => $assignment->id, 'ended_on' => null]);
    }

    public function test_an_assignment_starting_outside_the_groups_academic_year_is_rejected(): void
    {
        $this->seedReferenceData();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('must fall within');

        $this->assignments()->assignToGroup(
            $this->group('Green A'), $this->english, $this->teacher('Sarah'), $this->date('2020-01-15')
        );
    }

    public function test_an_end_date_before_the_start_date_is_rejected(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignments()->assignToGroup(
            $this->group('Green A'), $this->english, $this->teacher('Sarah'), $this->date('2026-09-01')
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cannot be before the start date');

        $this->assignments()->endAssignment($assignment, $this->date('2026-08-01'));
    }

    public function test_historical_assignment_periods_for_one_group_and_subject_cannot_overlap(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A');

        ClassSubject::create([
            'teaching_group_id' => $group->id, 'subject_id' => $this->english->id,
            'staff_id' => $this->teacher('Sarah')->id,
            'started_on' => '2026-07-01', 'ended_on' => '2026-12-31',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Assignment periods cannot overlap');

        $this->assignments()->assignToGroup($group, $this->english, $this->teacher('Eka'), $this->date('2026-10-01'));
    }

    public function test_adjacent_teacher_transitions_are_allowed(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A');

        ClassSubject::create([
            'teaching_group_id' => $group->id, 'subject_id' => $this->english->id,
            'staff_id' => $this->teacher('Sarah')->id,
            'started_on' => '2026-07-01', 'ended_on' => '2026-12-15',
        ]);

        $eka = $this->assignments()->assignToGroup($group, $this->english, $this->teacher('Eka'), $this->date('2026-12-16'));

        $this->assertTrue($eka->isActive());
        $this->assertSame(2, ClassSubject::where('teaching_group_id', $group->id)->count());
    }

    // ------------------------------------------------------ source safety

    public function test_a_class_backed_assignment_cannot_be_turned_into_a_group_backed_one(): void
    {
        $this->seedReferenceData();
        $assignment = ClassSubject::create([
            'class_id' => $this->schoolClass('Year 5', 'Year 5A')->id,
            'subject_id' => $this->english->id,
            'staff_id' => $this->teacher('Sarah')->id, 'started_on' => '2026-07-01',
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('roster source cannot be changed');

        $assignment->update(['class_id' => null, 'teaching_group_id' => $this->group('Green A')->id]);
    }

    public function test_a_group_backed_assignment_cannot_be_silently_moved_to_another_group(): void
    {
        $this->seedReferenceData();
        $assignment = ClassSubject::create([
            'teaching_group_id' => $this->group('Green A')->id,
            'subject_id' => $this->english->id,
            'staff_id' => $this->teacher('Sarah')->id, 'started_on' => '2026-07-01',
        ]);

        $this->expectException(\LogicException::class);

        $assignment->update(['teaching_group_id' => $this->group('Blue A')->id]);
    }

    /**
     * The supported way to change roster source: end the old assignment, open
     * the correct new one. History keeps both.
     */
    public function test_changing_roster_source_is_done_by_ending_and_creating(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $group = $this->group('Green A');
        $sarah = $this->teacher('Sarah');

        $classBacked = ClassSubject::create([
            'class_id' => $class->id, 'subject_id' => $this->english->id,
            'staff_id' => $sarah->id, 'started_on' => '2026-07-01',
        ]);

        $this->assignments()->endAssignment($classBacked, $this->date('2026-12-15'));
        $groupBacked = $this->assignments()->assignToGroup($group, $this->english, $sarah, $this->date('2026-12-16'));

        $this->assertFalse($classBacked->fresh()->isActive());
        $this->assertSame($class->id, $classBacked->fresh()->class_id, 'the old row still describes the class it served');
        $this->assertTrue($groupBacked->isTeachingGroupBacked());
    }

    // ------------------------------------------------------------- audit

    public function test_group_assignment_creation_is_audited(): void
    {
        $this->seedReferenceData();

        $before = $this->auditCount('created');
        $this->assignments()->assignToGroup(
            $this->group('Green A'), $this->english, $this->teacher('Sarah'), $this->date('2026-07-01')
        );

        $this->assertSame($before + 1, $this->auditCount('created'));
    }

    public function test_a_handover_records_both_the_close_and_the_new_assignment(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A');

        $this->assignments()->assignToGroup($group, $this->english, $this->teacher('Sarah'), $this->date('2026-07-01'));

        $createdBefore = $this->auditCount('created');
        $updatedBefore = $this->auditCount('updated');

        $this->assignments()->assignToGroup($group, $this->english, $this->teacher('Eka'), $this->date('2026-12-16'));

        $this->assertSame($updatedBefore + 1, $this->auditCount('updated'), 'old row closed');
        $this->assertSame($createdBefore + 1, $this->auditCount('created'), 'new row created');
    }

    public function test_ending_an_assignment_is_audited(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignments()->assignToGroup(
            $this->group('Green A'), $this->english, $this->teacher('Sarah'), $this->date('2026-09-01')
        );

        $before = $this->auditCount('updated');
        $this->assignments()->endAssignment($assignment, $this->date('2026-12-15'));

        $this->assertSame($before + 1, $this->auditCount('updated'));
    }

    // ---------------------------------------------------- authorization / UI

    public function test_an_admin_can_assign_a_subject_and_teacher_through_the_group_screen(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A');
        $eka = $this->teacher('Eka');

        Livewire::actingAs($this->userWithRole('admin_staff'))
            ->test(GroupShow::class, ['teachingGroup' => $group])
            ->set('subject_id', (string) $this->english->id)
            ->set('assignment_staff_id', (string) $eka->id)
            ->set('assignment_started_on', '2026-09-01')
            ->call('assignSubject')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('class_subject', [
            'teaching_group_id' => $group->id,
            'staff_id' => $eka->id,
            'class_id' => null,
            'ended_on' => null,
        ]);
    }

    public function test_a_principal_can_reassign_the_teacher_through_the_group_screen(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A');
        $sarah = $this->teacher('Sarah');
        $eka = $this->teacher('Eka');

        $original = $this->assignments()->assignToGroup($group, $this->english, $sarah, $this->date('2026-07-01'));

        Livewire::actingAs($this->userWithRole('principal'))
            ->test(GroupShow::class, ['teachingGroup' => $group])
            ->set('subject_id', (string) $this->english->id)
            ->set('assignment_staff_id', (string) $eka->id)
            ->set('assignment_started_on', '2026-12-16')
            ->call('assignSubject')
            ->assertHasNoErrors();

        $this->assertSame($sarah->id, $original->fresh()->staff_id);
        $this->assertSame('2026-12-15', $original->fresh()->ended_on->toDateString());
    }

    public function test_a_teacher_cannot_manage_group_assignments(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A');
        $teacherUser = $this->userWithRole('teacher');

        $this->assertFalse($teacherUser->can('update', $group));
        $this->assertFalse($teacherUser->can('view', $group));

        Livewire::actingAs($teacherUser)
            ->test(GroupShow::class, ['teachingGroup' => $group])
            ->assertForbidden();
    }

    /**
     * Step 2b changes no authorization for students. Teacher access still
     * flows only through administrative classes; teaching groups grant nothing.
     */
    public function test_student_policy_is_unchanged_by_teaching_group_assignments(): void
    {
        $this->seedReferenceData();
        $group = $this->group('Green A');
        $eka = $this->teacher('Eka');

        $user = User::create([
            'name' => 'Eka', 'email' => 'eka@rahai.test',
            'password' => bcrypt('password'), 'status' => 'active',
        ]);
        $user->assignRole('teacher');
        $eka->update(['user_id' => $user->id]);

        $student = Student::create([
            'student_number' => 'S-1', 'first_name' => 'Budi', 'last_name' => 'S',
            'date_of_birth' => '2015-01-01', 'gender' => 'male',
            'enrollment_date' => '2026-07-01', 'status' => 'active',
        ]);
        $group->memberships()->create(['student_id' => $student->id, 'started_on' => '2026-07-01']);

        $this->assignments()->assignToGroup($group, $this->english, $eka, $this->date('2026-07-01'));

        $this->assertFalse(
            $user->fresh()->can('view', $student),
            'teaching-group teaching must not yet grant access to the student record'
        );
    }

    // ----------------------------------------------------------- helpers

    private function seedReferenceData(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(GradeSeeder::class);
        $this->seed(PositionSeeder::class);

        $this->year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-07-01',
            'end_date' => '2027-06-30', 'is_current' => true,
        ]);

        $this->seed(EnglishProgrammeSeeder::class);

        $this->english = Subject::create(['name' => 'English']);
    }

    private function group(string $name, string $level = 'Green'): TeachingGroup
    {
        return TeachingGroup::firstOrCreate(
            ['academic_year_id' => $this->year->id, 'name' => $name],
            ['english_level_id' => \App\Models\EnglishLevel::where('name', $level)->first()?->id, 'status' => 'active'],
        );
    }

    private function schoolClass(string $grade, string $name): SchoolClass
    {
        return SchoolClass::create([
            'name' => $name,
            'grade_id' => Grade::where('name', $grade)->firstOrFail()->id,
            'academic_year_id' => $this->year->id,
        ]);
    }

    private function teacher(string $first): Staff
    {
        return Staff::firstOrCreate(
            ['staff_number' => 'T-'.strtoupper($first)],
            [
                'first_name' => $first, 'last_name' => 'Teacher',
                'position_id' => Position::firstOrFail()->id,
                'phone' => '0800000000',
                'hire_date' => '2020-01-01', 'status' => 'active',
            ],
        );
    }

    private function userWithRole(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role), 'email' => $role.'@rahai.test',
            'password' => bcrypt('password'), 'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function assignments(): TeachingAssignmentService
    {
        return app(TeachingAssignmentService::class);
    }

    private function date(string $value): Carbon
    {
        return Carbon::parse($value);
    }

    private function auditCount(string $action): int
    {
        return AuditLog::where('auditable_type', ClassSubject::class)->where('action', $action)->count();
    }
}
