<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\Attendance;
use App\Models\ClassStudent;
use App\Models\ClassSubject;
use App\Models\Grade;
use App\Models\Student;
use App\Services\AcademicRecordService;
use App\Services\ClassStudentService;
use App\Services\StudentGradeResolver;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Concerns\BuildsCommunicationFixtures;
use Tests\TestCase;

/**
 * Foundation F3 -- ClassStudent effective dating + date-aware roster
 * integrity. Covers the DB invariant (one current enrollment per Student,
 * system-wide), the half-open [enrolled_at, ended_on) boundary convention,
 * transfer/withdrawal, the resolver, and every downstream consumer that
 * needed to become genuinely date-aware: ClassSubject::rosterOn() (and
 * transitively Assessment::scoreSheetStudents()), Attendance Take/Report,
 * StudentGradeResolver, and Communication's current-audience resolution.
 */
class ClassStudentEffectiveDatingTest extends TestCase
{
    use BuildsCommunicationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCommunicationReferenceData();
    }

    private function service(): ClassStudentService
    {
        return app(ClassStudentService::class);
    }

    private function freshStudent(string $number): Student
    {
        return Student::create([
            'student_number' => $number, 'first_name' => $number, 'last_name' => 'Test',
            'date_of_birth' => '2015-01-01', 'gender' => 'male',
            'enrollment_date' => '2026-07-01', 'status' => 'active',
        ]);
    }

    private function classAssignment(\App\Models\SchoolClass $class, \App\Models\Subject $subject, \App\Models\Staff $staff): ClassSubject
    {
        return ClassSubject::create([
            'class_id' => $class->id, 'subject_id' => $subject->id,
            'staff_id' => $staff->id, 'started_on' => '2026-07-01',
        ]);
    }

    private function assessment(ClassSubject $assignment, string $periodName, string $date): Assessment
    {
        return Assessment::create([
            'class_subject_id' => $assignment->id,
            'academic_period_id' => $this->period($periodName)->id,
            'name' => 'Test', 'max_score' => 100, 'assessment_date' => $date,
        ]);
    }

    // ------------------------------------------------- DB current-enrollment

    public function test_the_database_allows_the_first_open_enrollment(): void
    {
        $class = $this->class('Year 5', 'Year 5A');
        $student = $this->freshStudent('STU-01');

        $this->service()->enroll($class, $student, Carbon::parse('2026-07-01'));

        $this->assertDatabaseHas('class_student', [
            'class_id' => $class->id, 'student_id' => $student->id, 'status' => 'active', 'ended_on' => null,
        ]);
    }

    public function test_the_database_rejects_a_second_open_enrollment_for_the_same_student(): void
    {
        $classA = $this->class('Year 5', 'Year 5A');
        $classB = $this->class('Year 5', 'Year 5B');
        $student = $this->freshStudent('STU-02');
        $this->service()->enroll($classA, $student, Carbon::parse('2026-07-01'));

        $this->expectException(QueryException::class);

        DB::table('class_student')->insert([
            'class_id' => $classB->id, 'student_id' => $student->id,
            'enrolled_at' => '2026-08-01', 'status' => 'active',
        ]);
    }

    public function test_a_closed_enrollment_plus_a_new_open_one_is_allowed(): void
    {
        $classA = $this->class('Year 5', 'Year 5A');
        $classB = $this->class('Year 5', 'Year 5B');
        $student = $this->freshStudent('STU-03');
        $this->service()->enroll($classA, $student, Carbon::parse('2026-07-01'));

        $this->service()->transfer($student, $classB, Carbon::parse('2026-08-15'));

        $this->assertSame(2, ClassStudent::where('student_id', $student->id)->count());
        $this->assertSame(1, ClassStudent::where('student_id', $student->id)->open()->count());
    }

    public function test_a_different_student_may_hold_an_open_enrollment_in_the_same_class(): void
    {
        $class = $this->class('Year 5', 'Year 5A');
        $a = $this->freshStudent('STU-04A');
        $b = $this->freshStudent('STU-04B');

        $this->service()->enroll($class, $a, Carbon::parse('2026-07-01'));
        $this->service()->enroll($class, $b, Carbon::parse('2026-07-01'));

        $this->assertSame(2, ClassStudent::where('class_id', $class->id)->open()->count());
    }

    // ---------------------------------------------------- status/date rules

    public function test_ending_an_enrollment_on_or_before_its_start_is_refused(): void
    {
        $class = $this->class('Year 5', 'Year 5A');
        $student = $this->freshStudent('STU-05');
        $this->service()->enroll($class, $student, Carbon::parse('2026-08-01'));

        $this->expectException(ValidationException::class);

        $this->service()->withdraw($student, Carbon::parse('2026-08-01'));
    }

    public function test_postgresql_rejects_an_inverted_date_range(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('class_student_date_range_check is PostgreSQL-only; SQLite lacks ALTER TABLE ADD CONSTRAINT.');
        }

        $class = $this->class('Year 5', 'Year 5A');
        $student = $this->freshStudent('STU-06');
        $row = $this->service()->enroll($class, $student, Carbon::parse('2026-08-01'));

        $this->expectException(QueryException::class);

        DB::table('class_student')->where('id', $row->id)->update(['ended_on' => '2026-07-01']);
    }

    // -------------------------------------------------------------- transfer

    public function test_a_transfer_closes_the_outgoing_row_and_opens_the_incoming_one(): void
    {
        $classA = $this->class('Year 5', 'Year 5A');
        $classB = $this->class('Year 5', 'Year 5B');
        $student = $this->freshStudent('STU-07');
        $this->service()->enroll($classA, $student, Carbon::parse('2026-07-01'));

        $this->service()->transfer($student, $classB, Carbon::parse('2026-08-15'));

        $outgoing = ClassStudent::where('student_id', $student->id)->where('class_id', $classA->id)->first();
        $incoming = ClassStudent::where('student_id', $student->id)->where('class_id', $classB->id)->first();

        $this->assertSame('transferred_out', $outgoing->status, 'A: status=transferred_out');
        $this->assertSame('2026-08-15', $outgoing->ended_on->toDateString(), 'A: ended_on set');
        $this->assertNotNull($incoming, 'B: row created');
        $this->assertSame('active', $incoming->status, 'B: status=active');
        $this->assertNull($incoming->ended_on, 'B: ended_on null');
        $this->assertSame(1, ClassStudent::where('student_id', $student->id)->open()->count(), 'exactly one open enrollment');
        $this->assertTrue($student->fresh()->currentClass()->is($classB));

        $this->assertDatabaseHas('audit_logs', ['auditable_type' => ClassStudent::class, 'auditable_id' => $outgoing->id, 'action' => 'updated']);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => ClassStudent::class, 'auditable_id' => $incoming->id, 'action' => 'created']);
    }

    public function test_enroll_refuses_a_second_open_class_and_directs_to_transfer(): void
    {
        $classA = $this->class('Year 5', 'Year 5A');
        $classB = $this->class('Year 5', 'Year 5B');
        $student = $this->freshStudent('STU-08');
        $this->service()->enroll($classA, $student, Carbon::parse('2026-07-01'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Use Transfer instead');

        $this->service()->enroll($classB, $student, Carbon::parse('2026-08-01'));
    }

    public function test_transfer_with_no_current_enrollment_is_refused(): void
    {
        $classB = $this->class('Year 5', 'Year 5B');
        $student = $this->freshStudent('STU-09');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Use Enroll instead');

        $this->service()->transfer($student, $classB, Carbon::parse('2026-08-01'));
    }

    // ------------------------------------------------------------ withdrawal

    public function test_withdrawal_closes_the_row_with_no_successor(): void
    {
        $class = $this->class('Year 5', 'Year 5A');
        $student = $this->freshStudent('STU-10');
        $this->service()->enroll($class, $student, Carbon::parse('2026-07-01'));

        $this->service()->withdraw($student, Carbon::parse('2026-09-01'));

        $row = ClassStudent::where('student_id', $student->id)->first();
        $this->assertSame('withdrawn', $row->status);
        $this->assertNotNull($row->ended_on);
        $this->assertSame(0, ClassStudent::where('student_id', $student->id)->open()->count());
        $this->assertNull($student->fresh()->currentClass());
    }

    // ---------------------------------------------------------- currentClass

    public function test_current_class_returns_null_when_none_is_enrolled(): void
    {
        $student = $this->freshStudent('STU-11');

        $this->assertNull($student->currentClass());
    }

    public function test_current_class_returns_the_open_enrollment(): void
    {
        $class = $this->class('Year 5', 'Year 5A');
        $student = $this->freshStudent('STU-12');
        $this->service()->enroll($class, $student, Carbon::parse('2026-07-01'));

        $this->assertTrue($student->fresh()->currentClass()->is($class));
    }

    public function test_current_class_ignores_historical_rows(): void
    {
        $classA = $this->class('Year 5', 'Year 5A');
        $classB = $this->class('Year 5', 'Year 5B');
        $student = $this->freshStudent('STU-13');
        $this->service()->enroll($classA, $student, Carbon::parse('2026-07-01'));
        $this->service()->transfer($student, $classB, Carbon::parse('2026-08-15'));

        $this->assertTrue($student->fresh()->currentClass()->is($classB));
    }

    public function test_current_class_fails_loud_if_more_than_one_row_is_open(): void
    {
        DB::statement('DROP INDEX class_student_current_enrollment_unique');
        $classA = $this->class('Year 5', 'Year 5A');
        $classB = $this->class('Year 5', 'Year 5B');
        $student = $this->freshStudent('STU-14');
        DB::table('class_student')->insert(['class_id' => $classA->id, 'student_id' => $student->id, 'enrolled_at' => '2026-07-01', 'status' => 'active']);
        DB::table('class_student')->insert(['class_id' => $classB->id, 'student_id' => $student->id, 'enrolled_at' => '2026-08-01', 'status' => 'active']);

        $this->expectException(\LogicException::class);

        $student->fresh()->currentClass();
    }

    // ---------------------------------------------------------- rosterOn(date)

    public function test_roster_on_date_reflects_a_transfer_boundary(): void
    {
        $classA = $this->class('Year 5', 'Year 5A');
        $classB = $this->class('Year 5', 'Year 5B');
        $student = $this->freshStudent('STU-15');
        $this->service()->enroll($classA, $student, Carbon::parse('2026-07-01'));
        $this->service()->transfer($student, $classB, Carbon::parse('2026-08-15'));

        $assignmentA = $this->classAssignment($classA, $this->subject('Maths'), $this->staff('sarah'));
        $assignmentB = $this->classAssignment($classB, $this->subject('Maths'), $this->staff('sarah'));

        // Before the transfer: only Class A's roster has the Student.
        $this->assertTrue($assignmentA->rosterOn(Carbon::parse('2026-08-10'))->contains('id', $student->id));
        $this->assertFalse($assignmentB->rosterOn(Carbon::parse('2026-08-10'))->contains('id', $student->id));

        // On the transfer date itself: only Class B counts (half-open convention).
        $this->assertFalse($assignmentA->rosterOn(Carbon::parse('2026-08-15'))->contains('id', $student->id));
        $this->assertTrue($assignmentB->rosterOn(Carbon::parse('2026-08-15'))->contains('id', $student->id));

        // After the transfer: only Class B.
        $this->assertFalse($assignmentA->rosterOn(Carbon::parse('2026-08-20'))->contains('id', $student->id));
        $this->assertTrue($assignmentB->rosterOn(Carbon::parse('2026-08-20'))->contains('id', $student->id));
    }

    // ---------------------------------------------------------- assessment

    public function test_assessment_roster_excludes_a_student_transferred_out_before_the_assessment_date(): void
    {
        $classA = $this->class('Year 5', 'Year 5A');
        $classB = $this->class('Year 5', 'Year 5B');
        $student = $this->freshStudent('STU-16');
        $this->service()->enroll($classA, $student, Carbon::parse('2026-07-01'));
        $this->service()->transfer($student, $classB, Carbon::parse('2026-08-15'));

        $assignmentA = $this->classAssignment($classA, $this->subject('Maths'), $this->staff('sarah'));
        $assignmentB = $this->classAssignment($classB, $this->subject('Maths'), $this->staff('sarah'));

        $before = $this->assessment($assignmentA, 'Semester 1', '2026-08-01');
        $after = $this->assessment($assignmentB, 'Semester 1', '2026-09-01');

        $this->assertTrue($before->scoreSheetStudents()->contains('id', $student->id));
        $this->assertTrue($after->scoreSheetStudents()->contains('id', $student->id));

        $afterOnOldClass = $this->assessment($assignmentA, 'Semester 1', '2026-09-01');
        $this->assertFalse($afterOnOldClass->scoreSheetStudents()->contains('id', $student->id));
    }

    public function test_an_already_scored_student_still_appears_after_transferring_out(): void
    {
        // A recorded mark must stay reportable even after the Student leaves
        // -- Assessment's own documented rule, still true post-F3.
        $classA = $this->class('Year 5', 'Year 5A');
        $classB = $this->class('Year 5', 'Year 5B');
        $student = $this->freshStudent('STU-17');
        $this->service()->enroll($classA, $student, Carbon::parse('2026-07-01'));

        $assignmentA = $this->classAssignment($classA, $this->subject('Maths'), $this->staff('sarah'));
        $assessment = $this->assessment($assignmentA, 'Semester 1', '2026-08-01');
        AssessmentResult::create(['assessment_id' => $assessment->id, 'student_id' => $student->id, 'score' => 77]);

        $this->service()->transfer($student, $classB, Carbon::parse('2026-08-15'));

        $this->assertTrue($assessment->fresh()->scoreSheetStudents()->contains('id', $student->id));
    }

    // ---------------------------------------------------------- attendance

    public function test_attendance_take_roster_is_date_aware_across_a_transfer(): void
    {
        $classA = $this->class('Year 5', 'Year 5A');
        $classB = $this->class('Year 5', 'Year 5B');
        $student = $this->freshStudent('STU-18');
        $this->service()->enroll($classA, $student, Carbon::parse('2026-07-01'));
        $this->service()->transfer($student, $classB, Carbon::parse('2026-08-15'));

        $this->assertTrue($classA->studentsOn(Carbon::parse('2026-08-10'))->contains('id', $student->id));
        $this->assertFalse($classA->studentsOn(Carbon::parse('2026-08-20'))->contains('id', $student->id));
        $this->assertTrue($classB->studentsOn(Carbon::parse('2026-08-20'))->contains('id', $student->id));
    }

    public function test_attendance_report_roster_includes_a_student_who_transferred_out_mid_range(): void
    {
        $classA = $this->class('Year 5', 'Year 5A');
        $classB = $this->class('Year 5', 'Year 5B');
        $student = $this->freshStudent('STU-19');
        $this->service()->enroll($classA, $student, Carbon::parse('2026-07-01'));
        $this->service()->transfer($student, $classB, Carbon::parse('2026-08-15'));

        $rangeIncludingTransfer = $classA->studentsEnrolledBetween(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));
        $this->assertTrue($rangeIncludingTransfer->contains('id', $student->id), 'transferred-out student still appears for the range they were enrolled');

        $rangeAfterTransfer = $classA->studentsEnrolledBetween(Carbon::parse('2026-08-16'), Carbon::parse('2026-08-31'));
        $this->assertFalse($rangeAfterTransfer->contains('id', $student->id), 'excluded from a range entirely after they left');
    }

    // ------------------------------------------------------------- grade

    public function test_grade_for_year_resolves_the_same_grade_across_a_mid_year_transfer(): void
    {
        $classA = $this->class('Year 5', 'Year 5A');
        $classB = $this->class('Year 5', 'Year 5B');
        $student = $this->freshStudent('STU-20');
        $this->service()->enroll($classA, $student, Carbon::parse('2026-07-01'));
        $this->service()->transfer($student, $classB, Carbon::parse('2026-08-15'));

        $grade = app(StudentGradeResolver::class)->gradeForYear($student, $this->year->id, $reason);

        $this->assertSame('Year 5', $grade->name);
        $this->assertNull($reason);
    }

    public function test_grade_on_resolves_the_grade_effective_on_a_backdated_date_not_today(): void
    {
        // The load-bearing point-in-time case: gradeOn() must answer for the
        // PAST date given, not the Student's current (post-transfer) class.
        $grade5 = Grade::where('name', 'Year 5')->first();
        $grade6 = Grade::create(['name' => 'Year 6', 'level_order' => 7]);
        $classA = $this->class('Year 5', 'Year 5A');
        $classB = \App\Models\SchoolClass::create(['name' => 'Year 6A', 'grade_id' => $grade6->id, 'academic_year_id' => $this->year->id]);
        $student = $this->freshStudent('STU-21');
        $this->service()->enroll($classA, $student, Carbon::parse('2026-07-01'));
        $this->service()->transfer($student, $classB, Carbon::parse('2026-08-15'));

        $pastGrade = app(StudentGradeResolver::class)->gradeOn($student, Carbon::parse('2026-08-01'), $reason);
        $currentGrade = app(StudentGradeResolver::class)->gradeOn($student, Carbon::parse('2026-09-01'), $reason);

        $this->assertTrue($pastGrade->is($grade5), 'resolves the grade as at the past date, not today');
        $this->assertTrue($currentGrade->is($grade6));
    }

    // -------------------------------------------------------- academic record

    public function test_resolve_class_stays_current_only_and_a_later_transfer_never_rewrites_the_published_snapshot(): void
    {
        $classA = $this->class('Year 5', 'Year 5A');
        $classB = $this->class('Year 5', 'Year 5B');
        $student = $this->freshStudent('STU-22');
        $this->service()->enroll($classA, $student, Carbon::parse('2026-07-01'));

        $assignment = $this->classAssignment($classA, $this->subject('Maths'), $this->staff('sarah'));
        $assessment = $this->assessment($assignment, 'Semester 1', '2026-08-01');
        AssessmentResult::create(['assessment_id' => $assessment->id, 'student_id' => $student->id, 'score' => 80]);

        $records = app(AcademicRecordService::class);

        $this->assertTrue($records->resolveClass($student, $this->year)->is($classA));

        $draft = $records->createDraft($student, $this->period('Semester 1'));
        $published = $records->publish($draft, $this->userWithRole('principal'));

        $this->assertSame('Year 5A', $published->class_name_snapshot);

        $this->service()->transfer($student, $classB, Carbon::parse('2026-08-15'));

        // resolveClass() is deliberately current-only: it now resolves the
        // Student's NEW class, not the one at publication time.
        $this->assertTrue($records->resolveClass($student->fresh(), $this->year)->is($classB));
        // The already-published snapshot is never rewritten by the transfer.
        $this->assertSame('Year 5A', $published->fresh()->class_name_snapshot);
    }

    // -------------------------------------------------------- communication

    public function test_communication_current_audience_transfers_with_the_student(): void
    {
        $classA = $this->class('Year 5', 'Year 5A');
        $classB = $this->class('Year 5', 'Year 5B');
        $student = $this->freshStudent('STU-23');
        $this->service()->enroll($classA, $student, Carbon::parse('2026-07-01'));
        $this->assignHomeroom($classA, $this->teacherStaff('sarah'));
        $this->assignHomeroom($classB, $this->teacherStaff('eka'));

        $scope = app(\App\Communications\TeacherAudienceScope::class);

        $this->assertTrue($scope->canTargetStudent($this->teacherStaff('sarah'), $student->id));
        $this->assertFalse($scope->canTargetStudent($this->teacherStaff('eka'), $student->id));

        $this->service()->transfer($student, $classB, Carbon::parse('2026-08-15'));

        $this->assertFalse($scope->canTargetStudent($this->teacherStaff('sarah'), $student->id), 'outgoing class teacher loses current audience');
        $this->assertTrue($scope->canTargetStudent($this->teacherStaff('eka'), $student->id), 'incoming class teacher gains current audience');
    }

    // -------------------------------------------------------- subject_teacher

    public function test_no_class_student_class_calls_academic_year_current_internally(): void
    {
        // Structural regression pin matching the same discipline already
        // applied to ManagementInsights in Foundation F1 -- explicit scope
        // in, never an implicit current() resolution.
        $offenders = collect(\Illuminate\Support\Facades\File::allFiles(app_path('Services')))
            ->filter(fn ($file) => str_contains($file->getFilename(), 'ClassStudentService'))
            ->filter(function ($file) {
                $code = \Illuminate\Support\Facades\File::get($file->getPathname());
                $code = preg_replace('#/\*.*?\*/#s', '', $code);
                $code = preg_replace('#//[^\n]*#', '', $code);

                return str_contains($code, 'AcademicYear::current(');
            });

        $this->assertTrue($offenders->isEmpty());
    }
}
