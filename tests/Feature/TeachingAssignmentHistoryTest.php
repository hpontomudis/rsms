<?php

namespace Tests\Feature;

use App\Livewire\Academics\ReportCard;
use App\Livewire\Classes\Show as ClassShow;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\ClassSubject;
use App\Models\Grade;
use App\Models\Position;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step 0 regression suite: effective-dated teaching assignments.
 *
 * Before effective dating, reassigning a subject mutated class_subject.staff_id
 * in place, which retroactively re-attributed every historical assessment to
 * the incoming teacher and transferred edit rights along with it. These tests
 * pin the corrected behaviour: reassignment CLOSES the outgoing assignment and
 * OPENS a new one, and history keeps pointing at the assignment that was
 * actually in force.
 */
class TeachingAssignmentHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_reassignment_closes_the_previous_assignment_instead_of_mutating_it(): void
    {
        $c = $this->makeContext();

        $budiAssignmentId = $c['budiAssignment']->id;
        $this->reassignTo($c, $c['maria']);

        $budiAssignment = ClassSubject::find($budiAssignmentId);

        // Same row, same teacher -- only now closed.
        $this->assertNotNull($budiAssignment, "Budi's assignment row must survive reassignment");
        $this->assertSame($c['budi']->id, $budiAssignment->staff_id, 'staff_id must NOT be mutated');
        $this->assertNotNull($budiAssignment->ended_on, "Budi's assignment must be closed");
        $this->assertFalse($budiAssignment->isActive());
    }

    public function test_successor_receives_a_new_active_assignment_row(): void
    {
        $c = $this->makeContext();
        $this->reassignTo($c, $c['maria']);

        $active = ClassSubject::where('class_id', $c['class']->id)
            ->where('subject_id', $c['subject']->id)
            ->active()
            ->get();

        $this->assertCount(1, $active);
        $this->assertSame($c['maria']->id, $active->first()->staff_id);
        $this->assertNotSame($c['budiAssignment']->id, $active->first()->id, 'must be a NEW row');
    }

    public function test_historical_assessment_still_resolves_to_the_original_teacher(): void
    {
        $c = $this->makeContext();
        $assessment = $this->makeAssessment($c['budiAssignment'], 'Budi Midterm');

        $this->reassignTo($c, $c['maria']);

        // The whole point of the fix: January's work is still Budi's in March.
        $this->assertSame(
            $c['budi']->id,
            $assessment->fresh()->classSubject->staff_id,
            "Budi's assessment must not be re-attributed to Maria"
        );
    }

    public function test_successor_cannot_edit_the_predecessors_historical_assessment(): void
    {
        $c = $this->makeContext();
        $assessment = $this->makeAssessment($c['budiAssignment'], 'Budi Midterm');

        $this->reassignTo($c, $c['maria']);

        $this->assertFalse(
            $c['mariaUser']->can('recordScores', $assessment->fresh()),
            'Maria must not inherit edit rights over work she did not record'
        );
    }

    public function test_predecessor_can_view_but_not_modify_work_after_handover(): void
    {
        $c = $this->makeContext();
        $assessment = $this->makeAssessment($c['budiAssignment'], 'Budi Midterm');

        // Before handover: Budi can both view and record.
        $this->assertTrue($c['budiUser']->can('view', $assessment));
        $this->assertTrue($c['budiUser']->can('recordScores', $assessment));

        $this->reassignTo($c, $c['maria']);
        $assessment = $assessment->fresh();

        // After handover: read survives, write does not.
        $this->assertTrue($c['budiUser']->can('view', $assessment), 'Budi keeps read access to his own history');
        $this->assertFalse($c['budiUser']->can('recordScores', $assessment), 'closed assignments are frozen');
    }

    public function test_admin_retains_write_access_to_historical_assessments_for_corrections(): void
    {
        $c = $this->makeContext();
        $assessment = $this->makeAssessment($c['budiAssignment'], 'Budi Midterm');

        $this->reassignTo($c, $c['maria']);

        $this->assertTrue($c['adminUser']->can('recordScores', $assessment->fresh()));
        $this->assertTrue($c['adminUser']->can('view', $assessment->fresh()));
    }

    public function test_database_permits_only_one_active_assignment_per_class_and_subject(): void
    {
        $c = $this->makeContext();

        // The partial unique index -- not just app logic -- must reject a
        // second concurrently-active assignment.
        $this->expectException(QueryException::class);

        ClassSubject::create([
            'class_id' => $c['class']->id,
            'subject_id' => $c['subject']->id,
            'staff_id' => $c['maria']->id,
            'started_on' => now()->toDateString(),
        ]);
    }

    public function test_multiple_closed_assignments_are_permitted_for_the_same_class_and_subject(): void
    {
        $c = $this->makeContext();

        $this->reassignTo($c, $c['maria']);
        $this->reassignTo($c, $c['budi']);   // handed back again

        $all = ClassSubject::where('class_id', $c['class']->id)
            ->where('subject_id', $c['subject']->id)
            ->get();

        $this->assertCount(3, $all, 'full assignment lineage is retained');
        $this->assertCount(2, $all->whereNotNull('ended_on'), 'two closed assignments');
        $this->assertCount(1, $all->whereNull('ended_on'), 'exactly one active');
    }

    public function test_report_card_lists_a_subject_once_and_merges_results_across_teachers(): void
    {
        $c = $this->makeContext();

        // Budi records 80%, then hands over; Maria records 100% on the same
        // subject. The report card must show ONE Mathematics row at 90%.
        $budiAssessment = $this->makeAssessment($c['budiAssignment'], 'Term 1 Test');
        $budiAssessment->results()->create(['student_id' => $c['student']->id, 'score' => 80]);

        $this->reassignTo($c, $c['maria']);
        $mariaAssignment = ClassSubject::where('class_id', $c['class']->id)->active()->first();

        $mariaAssessment = $this->makeAssessment($mariaAssignment, 'Term 1 Project');
        $mariaAssessment->results()->create(['student_id' => $c['student']->id, 'score' => 100]);

        $rows = Livewire::actingAs($c['adminUser'])
            ->test(ReportCard::class, ['student' => $c['student']])
            ->viewData('rows');

        $maths = $rows->filter(fn ($r) => $r->subject->name === 'Mathematics');

        $this->assertCount(1, $maths, 'Mathematics must appear once, not once per teacher');
        $this->assertEquals(90, $maths->first()->termAverages['Term 1'], 'results merged across both assignments');
    }

    public function test_class_subject_changes_are_audit_logged(): void
    {
        $c = $this->makeContext();

        $auditsFor = fn () => AuditLog::where('auditable_type', ClassSubject::class)->count();
        $before = $auditsFor();

        $this->reassignTo($c, $c['maria']);

        // One 'updated' (Budi's row closed) + one 'created' (Maria's row).
        $this->assertSame($before + 2, $auditsFor());

        $closed = AuditLog::where('auditable_type', ClassSubject::class)
            ->where('action', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($closed);
        $this->assertArrayHasKey('ended_on', $closed->new_values);
    }

    public function test_an_assignment_with_history_is_closed_rather_than_deleted_on_removal(): void
    {
        $c = $this->makeContext();
        $this->makeAssessment($c['budiAssignment'], 'Budi Midterm');

        Livewire::actingAs($c['adminUser'])
            ->test(ClassShow::class, ['schoolClass' => $c['class']])
            ->call('removeSubject', $c['budiAssignment']->id);

        $assignment = ClassSubject::find($c['budiAssignment']->id);

        $this->assertNotNull($assignment, 'assignment with assessments must not be hard-deleted');
        $this->assertNotNull($assignment->ended_on, 'it must be closed instead');
    }

    public function test_the_class_screen_shows_only_active_assignments(): void
    {
        $c = $this->makeContext();
        $this->reassignTo($c, $c['maria']);

        $classSubjects = Livewire::actingAs($c['adminUser'])
            ->test(ClassShow::class, ['schoolClass' => $c['class']])
            ->viewData('classSubjects');

        $this->assertCount(1, $classSubjects);
        $this->assertSame($c['maria']->id, $classSubjects->first()->staff_id);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Year 5A / Mathematics, currently assigned to Budi, with Maria available
     * as a successor, an admin, and one enrolled student.
     */
    private function makeContext(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $ay = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_current' => true,
        ]);
        $grade = Grade::create(['name' => 'Year 5', 'level_order' => 6]);
        $class = SchoolClass::create(['name' => 'Year 5A', 'grade_id' => $grade->id, 'academic_year_id' => $ay->id]);
        $subject = Subject::create(['name' => 'Mathematics', 'grade_id' => $grade->id]);
        $position = Position::create(['title' => 'Subject Teacher']);

        [$budiUser, $budi] = $this->makeTeacher('Budi', 'Santoso', $position);
        [$mariaUser, $maria] = $this->makeTeacher('Maria', 'Lestari', $position);

        $adminUser = User::factory()->create();
        $adminUser->assignRole('admin_staff');

        $student = Student::create([
            'student_number' => 'STU-001', 'first_name' => 'Andi', 'last_name' => 'Wijaya',
            'date_of_birth' => '2015-03-12', 'gender' => 'male', 'enrollment_date' => '2026-07-01',
        ]);
        $class->students()->attach($student->id, ['enrolled_at' => '2026-07-01', 'status' => 'active']);

        $budiAssignment = ClassSubject::create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'staff_id' => $budi->id,
            'started_on' => '2026-07-01',
        ]);

        return compact(
            'ay', 'grade', 'class', 'subject', 'budiUser', 'budi',
            'mariaUser', 'maria', 'adminUser', 'student', 'budiAssignment'
        );
    }

    private function makeTeacher(string $first, string $last, Position $position): array
    {
        $user = User::factory()->create();
        $user->assignRole('teacher');

        $staff = Staff::create([
            'staff_number' => 'STF-'.uniqid(),
            'first_name' => $first,
            'last_name' => $last,
            'position_id' => $position->id,
            'phone' => '0812-0000-0001',
            'hire_date' => '2020-07-01',
            'user_id' => $user->id,
        ]);

        return [$user, $staff];
    }

    /**
     * Reassign through the real Livewire component, so the production
     * close-and-create path is what's under test.
     */
    private function reassignTo(array $c, Staff $newTeacher): void
    {
        Livewire::actingAs($c['adminUser'])
            ->test(ClassShow::class, ['schoolClass' => $c['class']])
            ->set('subject_id', (string) $c['subject']->id)
            ->set('subject_teacher_id', (string) $newTeacher->id)
            ->call('assignSubject');
    }

    private function makeAssessment(ClassSubject $classSubject, string $name): Assessment
    {
        return $classSubject->assessments()->create([
            'name' => $name,
            'term' => 'Term 1',
            'max_score' => 100,
            'assessment_date' => now()->toDateString(),
        ]);
    }
}
