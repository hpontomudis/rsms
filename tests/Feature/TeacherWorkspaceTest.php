<?php

namespace Tests\Feature;

use App\Livewire\Teaching\MyAssignments;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Assessment;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step 2e: a teacher's own teaching assignments, in one place.
 *
 * Closes the Step 2c gap where a group teacher could only reach their
 * assessments by typing the URL. Grants no new access to anything else.
 */
class TeacherWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year;

    private Subject $english;

    private Subject $maths;

    // --------------------------------------------------------------- identity

    public function test_the_workspace_resolves_the_signed_in_user_to_their_own_staff_record(): void
    {
        $this->seedReferenceData();
        $sarah = $this->teacher('Sarah');
        $sarahUser = $this->teacherUser('Sarah', $sarah);

        $this->classAssignment($this->schoolClass('Year 5', 'Year 5A'), $this->maths, $sarah);

        $active = $this->workspace($sarahUser, 'active');

        $this->assertCount(1, $active);
        $this->assertSame($sarah->id, $active->first()->staff_id);
    }

    /**
     * staff.user_id carries no unique index, so two staff rows can share a
     * login. User::staff() is a HasOne and would silently pick one -- showing
     * a teacher somebody else's work. The workspace refuses instead.
     */
    public function test_an_ambiguous_staff_mapping_shows_nothing_rather_than_guessing(): void
    {
        $this->seedReferenceData();
        $sarah = $this->teacher('Sarah');
        $sarahUser = $this->teacherUser('Sarah', $sarah);

        // A second staff row wrongly linked to the same login.
        $this->teacher('Impostor')->update(['user_id' => $sarahUser->id]);

        $this->classAssignment($this->schoolClass('Year 5', 'Year 5A'), $this->maths, $sarah);

        $component = Livewire::actingAs($sarahUser->fresh())->test(MyAssignments::class);

        $this->assertSame('ambiguous_staff', $component->viewData('problem'));
        $this->assertCount(0, $component->viewData('active'));
    }

    public function test_a_user_with_no_staff_record_sees_no_assignments(): void
    {
        $this->seedReferenceData();
        $sarah = $this->teacher('Sarah');
        $this->classAssignment($this->schoolClass('Year 5', 'Year 5A'), $this->maths, $sarah);

        $unlinked = User::create([
            'name' => 'Unlinked', 'email' => 'unlinked@rahai.test',
            'password' => bcrypt('password'), 'status' => 'active',
        ]);
        $unlinked->assignRole('teacher');

        $component = Livewire::actingAs($unlinked)->test(MyAssignments::class);

        $this->assertSame('no_staff', $component->viewData('problem'));
        $this->assertCount(0, $component->viewData('active'));
    }

    // ------------------------------------------------------ active assignments

    public function test_a_teacher_sees_both_their_class_and_their_teaching_group(): void
    {
        $this->seedReferenceData();
        $sarah = $this->teacher('Sarah');
        $sarahUser = $this->teacherUser('Sarah', $sarah);

        $classBacked = $this->classAssignment($this->schoolClass('Year 5', 'Year 5A'), $this->maths, $sarah);
        $groupBacked = $this->groupAssignment($this->group('Green A', 'Green'), $this->english, $sarah);

        $ids = $this->workspace($sarahUser, 'active')->pluck('id');

        $this->assertContains($classBacked->id, $ids);
        $this->assertContains($groupBacked->id, $ids);
    }

    public function test_a_teacher_does_not_see_another_teachers_class_assignment(): void
    {
        $this->seedReferenceData();
        $sarah = $this->teacher('Sarah');
        $sarahUser = $this->teacherUser('Sarah', $sarah);

        $ekasClass = $this->classAssignment($this->schoolClass('Year 5', 'Year 5B'), $this->maths, $this->teacher('Eka'));

        $this->assertNotContains($ekasClass->id, $this->workspace($sarahUser, 'active')->pluck('id'));
    }

    /**
     * Sharing a subject, a grade or an English programme is not a reason to
     * see someone else's assignment.
     */
    public function test_a_teacher_does_not_see_another_teachers_group_assignment_in_the_same_programme(): void
    {
        $this->seedReferenceData();
        $sarah = $this->teacher('Sarah');
        $sarahUser = $this->teacherUser('Sarah', $sarah);

        $this->groupAssignment($this->group('Green A', 'Green'), $this->english, $sarah);
        $ekasGroup = $this->groupAssignment($this->group('Blue A', 'Blue'), $this->english, $this->teacher('Eka'));

        $ids = $this->workspace($sarahUser, 'active')->pluck('id');

        $this->assertNotContains($ekasGroup->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_programme_and_level_context_is_derivable_for_a_group_assignment(): void
    {
        $this->seedReferenceData();
        $sarah = $this->teacher('Sarah');
        $sarahUser = $this->teacherUser('Sarah', $sarah);

        $this->groupAssignment($this->group('Green A', 'Green'), $this->english, $sarah);

        $assignment = $this->workspace($sarahUser, 'active')->first();
        $level = $assignment->teachingGroup->englishLevel;

        $this->assertSame('Green', $level->name);
        $this->assertSame('Primary English Programme', $level->programme->name);
        $this->assertSame('Teaching Group', $assignment->rosterLabel());
    }

    /**
     * Senior High English is an ordinary class-backed assignment: no level, no
     * programme. Programme context comes from the roster, not from the subject.
     */
    public function test_a_class_backed_english_assignment_carries_no_programme_context(): void
    {
        $this->seedReferenceData();
        $sarah = $this->teacher('Sarah');
        $sarahUser = $this->teacherUser('Sarah', $sarah);

        $this->classAssignment($this->schoolClass('Year 10', 'Year 10A'), $this->english, $sarah);

        $assignment = $this->workspace($sarahUser, 'active')->first();

        $this->assertNull($assignment->teachingGroup);
        $this->assertSame('Class', $assignment->rosterLabel());
        $this->assertSame('Year 10A', $assignment->displayName());
    }

    // -------------------------------------------------- historical assignments

    public function test_a_closed_assignment_moves_to_the_historical_section(): void
    {
        $this->seedReferenceData();
        $sarah = $this->teacher('Sarah');
        $sarahUser = $this->teacherUser('Sarah', $sarah);

        $assignment = $this->groupAssignment($this->group('Green A', 'Green'), $this->english, $sarah);
        $assignment->update(['ended_on' => '2026-12-15']);

        $component = Livewire::actingAs($sarahUser)->test(MyAssignments::class);

        $this->assertCount(0, $component->viewData('active'));
        $this->assertSame([$assignment->id], $component->viewData('historical')->pluck('id')->all());
    }

    public function test_a_closed_assignment_keeps_its_assessments_readable_but_not_writable(): void
    {
        $this->seedReferenceData();
        $sarah = $this->teacher('Sarah');
        $sarahUser = $this->teacherUser('Sarah', $sarah);

        $assignment = $this->groupAssignment($this->group('Green A', 'Green'), $this->english, $sarah);
        $assessment = $this->assessment($assignment);
        $assignment->update(['ended_on' => '2026-12-15']);

        // Still listed, still linked -- Step 0's read/write split intact.
        $this->assertTrue($sarahUser->can('viewFor', [Assessment::class, $assignment->fresh()]));
        $this->assertTrue($sarahUser->can('view', $assessment->fresh()));
        $this->assertFalse($sarahUser->can('createFor', [Assessment::class, $assignment->fresh()]));
        $this->assertFalse($sarahUser->can('recordScores', $assessment->fresh()));
    }

    public function test_an_active_assignment_keeps_its_assessment_entry_point(): void
    {
        $this->seedReferenceData();
        $sarah = $this->teacher('Sarah');
        $sarahUser = $this->teacherUser('Sarah', $sarah);

        $assignment = $this->groupAssignment($this->group('Green A', 'Green'), $this->english, $sarah);

        $canAssess = Livewire::actingAs($sarahUser)->test(MyAssignments::class)->viewData('canAssess');

        $this->assertTrue($canAssess($assignment));
        $this->assertTrue($sarahUser->can('createFor', [Assessment::class, $assignment]));
    }

    // ------------------------------------------------------------ academic year

    public function test_assignments_are_filtered_by_the_selected_academic_year(): void
    {
        $this->seedReferenceData();
        $sarah = $this->teacher('Sarah');
        $sarahUser = $this->teacherUser('Sarah', $sarah);

        $thisYear = $this->classAssignment($this->schoolClass('Year 5', 'Year 5A'), $this->maths, $sarah);

        $nextYear = AcademicYear::create([
            'name' => '2027/2028', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'is_current' => false,
        ]);
        $nextYearClass = SchoolClass::create([
            'name' => 'Year 6A', 'grade_id' => Grade::where('name', 'Year 6')->firstOrFail()->id,
            'academic_year_id' => $nextYear->id,
        ]);
        $nextYearAssignment = ClassSubject::create([
            'class_id' => $nextYearClass->id, 'subject_id' => $this->maths->id,
            'staff_id' => $sarah->id, 'started_on' => '2027-07-01',
        ]);

        $current = Livewire::actingAs($sarahUser)->test(MyAssignments::class);
        $this->assertSame([$thisYear->id], $current->viewData('active')->pluck('id')->all());

        $later = Livewire::actingAs($sarahUser)->test(MyAssignments::class)
            ->set('academic_year_id', (string) $nextYear->id);
        $this->assertSame([$nextYearAssignment->id], $later->viewData('active')->pluck('id')->all());
    }

    public function test_the_year_comes_from_the_roster_source_for_both_kinds(): void
    {
        $this->seedReferenceData();
        $sarah = $this->teacher('Sarah');
        $sarahUser = $this->teacherUser('Sarah', $sarah);

        $this->classAssignment($this->schoolClass('Year 5', 'Year 5A'), $this->maths, $sarah);
        $this->groupAssignment($this->group('Green A', 'Green'), $this->english, $sarah);

        foreach ($this->workspace($sarahUser, 'active') as $assignment) {
            $this->assertSame($this->year->id, $assignment->academicYear()->id);
        }
    }

    // ---------------------------------------------------------------- security

    public function test_the_workspace_needs_no_student_policy_access(): void
    {
        $this->seedReferenceData();
        $class = $this->schoolClass('Year 5', 'Year 5A');
        $andi = $this->student('Andi', $class);

        // Sarah teaches a GROUP only -- she teaches no class this student is in,
        // so StudentPolicy still refuses her the student's profile.
        $sarah = $this->teacher('Sarah');
        $sarahUser = $this->teacherUser('Sarah', $sarah);

        $green = $this->group('Green A', 'Green');
        $green->memberships()->create(['student_id' => $andi->id, 'started_on' => '2026-07-01']);
        $this->groupAssignment($green, $this->english, $sarah);

        // The workspace works...
        $this->assertCount(1, $this->workspace($sarahUser, 'active'));

        // ...without granting any access to the student record.
        $this->assertFalse($sarahUser->can('view', $andi));
    }

    public function test_a_teacher_cannot_reach_another_teachers_assignments_by_changing_the_year(): void
    {
        $this->seedReferenceData();
        $sarah = $this->teacher('Sarah');
        $sarahUser = $this->teacherUser('Sarah', $sarah);

        $this->classAssignment($this->schoolClass('Year 5', 'Year 5B'), $this->maths, $this->teacher('Eka'));

        // The only URL-bound input is the year; it cannot select a teacher.
        foreach (AcademicYear::pluck('id') as $yearId) {
            $active = Livewire::actingAs($sarahUser)->test(MyAssignments::class)
                ->set('academic_year_id', (string) $yearId)
                ->viewData('active');

            $this->assertCount(0, $active);
        }
    }

    public function test_the_workspace_carries_no_assignment_management_actions(): void
    {
        $this->seedReferenceData();
        $sarah = $this->teacher('Sarah');
        $sarahUser = $this->teacherUser('Sarah', $sarah);

        $group = $this->group('Green A', 'Green');
        $this->groupAssignment($group, $this->english, $sarah);

        $component = Livewire::actingAs($sarahUser)->test(MyAssignments::class);

        // No reassign/end methods exist here, and the management screen that
        // has them still refuses this teacher.
        $this->assertFalse(method_exists(MyAssignments::class, 'assignSubject'));
        $this->assertFalse(method_exists(MyAssignments::class, 'endAssignment'));
        $this->assertFalse($sarahUser->can('update', $group));
        $component->assertOk();
    }

    public function test_a_user_without_academics_view_cannot_open_the_workspace(): void
    {
        $this->seedReferenceData();

        $finance = User::create([
            'name' => 'Finance', 'email' => 'finance@rahai.test',
            'password' => bcrypt('password'), 'status' => 'active',
        ]);
        $finance->assignRole('finance_staff');

        Livewire::actingAs($finance)->test(MyAssignments::class)->assertForbidden();
    }

    // -------------------------------------------------------------- regression

    /**
     * Preflight follow-up from Step 2d: the class participation path is
     * student-specific. Pinned here so it cannot silently become
     * "every class in the year".
     */
    public function test_a_student_does_not_inherit_subjects_from_another_class_in_the_same_year(): void
    {
        $this->seedReferenceData();
        $classA = $this->schoolClass('Year 5', 'Year 5A');
        $classB = $this->schoolClass('Year 5', 'Year 5B');

        $andi = $this->student('Andi', $classA);

        // Only class B teaches Mathematics.
        $this->classAssignment($classB, $this->maths, $this->teacher('Eka'));

        $rows = Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Academics\ReportCard::class, ['student' => $andi])
            ->set('academic_year_id', (string) $this->year->id)
            ->viewData('rows');

        $this->assertNotContains('Mathematics', $rows->pluck('subject.name')->all());
    }

    // ---------------------------------------------------------------- helpers

    private function workspace(User $user, string $key)
    {
        return Livewire::actingAs($user)->test(MyAssignments::class)->viewData($key);
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

        $this->seed(EnglishProgrammeSeeder::class);

        $this->english = Subject::create(['name' => 'English']);
        $this->maths = Subject::create(['name' => 'Mathematics']);
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

    private function classAssignment(SchoolClass $class, Subject $subject, Staff $teacher): ClassSubject
    {
        return ClassSubject::create([
            'class_id' => $class->id, 'subject_id' => $subject->id,
            'staff_id' => $teacher->id, 'started_on' => '2026-07-01',
        ]);
    }

    private function groupAssignment(TeachingGroup $group, Subject $subject, Staff $teacher): ClassSubject
    {
        return ClassSubject::create([
            'teaching_group_id' => $group->id, 'subject_id' => $subject->id,
            'staff_id' => $teacher->id, 'started_on' => '2026-07-01',
        ]);
    }

    private function assessment(ClassSubject $assignment): Assessment
    {
        return Assessment::create([
            'class_subject_id' => $assignment->id,
            'academic_period_id' => AcademicPeriod::where('academic_year_id', $this->year->id)->firstOrFail()->id,
            'name' => 'Test', 'max_score' => 100, 'assessment_date' => '2026-11-01',
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

    private function teacherUser(string $first, Staff $staff): User
    {
        $user = User::create([
            'name' => $first, 'email' => strtolower($first).'@rahai.test',
            'password' => bcrypt('password'), 'status' => 'active',
        ]);
        $user->assignRole('teacher');
        $staff->update(['user_id' => $user->id]);

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
}
