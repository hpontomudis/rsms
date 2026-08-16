<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\ClassTeacher;
use App\Services\AcademicRecordService;
use App\Services\ClassTeacherService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Concerns\BuildsCommunicationFixtures;
use Tests\TestCase;

/**
 * Foundation F2 -- ClassTeacher effective dating. Covers the DB invariants
 * (homeroom singleton, assistant plurality, date-order), the transactional
 * handover, the resolver, subject_teacher deprecation, and the two
 * authorization surfaces (Communication, Attendance) that must close
 * together. The Communication-specific authority-transfer assertions live in
 * CommunicationAudienceTest (the inverted stale-handover regression); this
 * file adds the Attendance side and everything else.
 */
class ClassTeacherEffectiveDatingTest extends TestCase
{
    use BuildsCommunicationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCommunicationReferenceData();
    }

    private function service(): ClassTeacherService
    {
        return app(ClassTeacherService::class);
    }

    // ------------------------------------------------------ DB: homeroom

    public function test_the_database_allows_the_first_open_homeroom_row(): void
    {
        $class = $this->class('Year 5', 'Year 5A');
        $sarah = $this->teacherStaff('sarah');

        $this->assignHomeroom($class, $sarah);

        $this->assertDatabaseHas('class_teacher', [
            'class_id' => $class->id, 'staff_id' => $sarah->id, 'role' => 'homeroom', 'ended_on' => null,
        ]);
    }

    public function test_the_database_rejects_a_second_open_homeroom_row_for_the_same_class(): void
    {
        $class = $this->class('Year 5', 'Year 5A');
        $sarah = $this->teacherStaff('sarah');
        $eka = $this->teacherStaff('eka');
        $this->assignHomeroom($class, $sarah);

        $this->expectException(QueryException::class);

        DB::table('class_teacher')->insert([
            'class_id' => $class->id, 'staff_id' => $eka->id, 'role' => 'homeroom', 'started_on' => today(),
        ]);
    }

    public function test_a_closed_historical_homeroom_plus_a_new_open_one_is_allowed(): void
    {
        $class = $this->class('Year 5', 'Year 5A');
        $sarah = $this->teacherStaff('sarah');
        $eka = $this->teacherStaff('eka');
        $this->assignHomeroom($class, $sarah);

        $this->handoverHomeroom($class, $eka);

        $this->assertSame(2, ClassTeacher::where('class_id', $class->id)->where('role', 'homeroom')->count());
        $this->assertSame(1, ClassTeacher::where('class_id', $class->id)->where('role', 'homeroom')->open()->count());
    }

    public function test_the_same_homeroom_role_on_a_different_class_is_allowed(): void
    {
        $classA = $this->class('Year 5', 'Year 5A');
        $classB = $this->class('Year 5', 'Year 5B');
        $sarah = $this->teacherStaff('sarah');

        $this->assignHomeroom($classA, $sarah);
        $this->assignHomeroom($classB, $sarah);

        $this->assertDatabaseHas('class_teacher', ['class_id' => $classA->id, 'staff_id' => $sarah->id, 'role' => 'homeroom']);
        $this->assertDatabaseHas('class_teacher', ['class_id' => $classB->id, 'staff_id' => $sarah->id, 'role' => 'homeroom']);
    }

    // ----------------------------------------------------- DB: assistant

    public function test_two_open_assistant_rows_on_the_same_class_are_allowed(): void
    {
        // Deliberately proving the homeroom singleton rule was NOT applied
        // globally -- assistant is plural by design (Foundation F2 §4).
        $class = $this->class('Year 5', 'Year 5A');
        $sarah = $this->teacherStaff('sarah');
        $eka = $this->teacherStaff('eka');

        $this->service()->assignAssistant($class, $sarah);
        $this->service()->assignAssistant($class, $eka);

        $this->assertSame(2, ClassTeacher::where('class_id', $class->id)->where('role', 'assistant')->open()->count());
    }

    // -------------------------------------------------------- handover

    public function test_a_homeroom_handover_closes_the_outgoing_row_and_opens_the_incoming_one(): void
    {
        $class = $this->class('Year 5', 'Year 5A');
        $sarah = $this->teacherStaff('sarah');
        $eka = $this->teacherStaff('eka');
        $this->assignHomeroom($class, $sarah);

        $this->handoverHomeroom($class, $eka);

        $outgoing = ClassTeacher::where('class_id', $class->id)->where('staff_id', $sarah->id)->where('role', 'homeroom')->first();
        $incoming = ClassTeacher::where('class_id', $class->id)->where('staff_id', $eka->id)->where('role', 'homeroom')->first();

        $this->assertNotNull($outgoing, 'A: outgoing row remains');
        $this->assertNotNull($outgoing->ended_on, 'B: outgoing row is closed');
        $this->assertNotNull($incoming, 'C: incoming row exists');
        $this->assertNull($incoming->ended_on, 'D: incoming row is open');
        $this->assertSame(1, ClassTeacher::where('class_id', $class->id)->where('role', 'homeroom')->open()->count(), 'E: exactly one open homeroom');
        $this->assertTrue($class->fresh()->homeroomTeacher()->is($eka), 'identity preserved: resolver returns the incoming teacher');

        $this->assertDatabaseHas('audit_logs', ['auditable_type' => ClassTeacher::class, 'auditable_id' => $outgoing->id, 'action' => 'updated']);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => ClassTeacher::class, 'auditable_id' => $incoming->id, 'action' => 'created']);
    }

    public function test_setting_the_already_current_homeroom_teacher_is_an_idempotent_no_op(): void
    {
        $class = $this->class('Year 5', 'Year 5A');
        $sarah = $this->teacherStaff('sarah');
        $this->assignHomeroom($class, $sarah);
        $before = ClassTeacher::where('class_id', $class->id)->where('role', 'homeroom')->open()->first();

        $result = $this->service()->setHomeroom($class, $sarah);

        $this->assertTrue($result->is($before));
        $this->assertSame(1, ClassTeacher::where('class_id', $class->id)->where('role', 'homeroom')->count());
    }

    public function test_ending_homeroom_with_no_successor_leaves_the_class_with_no_current_homeroom(): void
    {
        $class = $this->class('Year 5', 'Year 5A');
        $sarah = $this->teacherStaff('sarah');
        $this->assignHomeroom($class, $sarah);

        $this->service()->endHomeroom($class);

        $this->assertNull($class->fresh()->homeroomTeacher());
        $this->assertSame(0, ClassTeacher::where('class_id', $class->id)->where('role', 'homeroom')->open()->count());
        $this->assertSame(1, ClassTeacher::where('class_id', $class->id)->where('role', 'homeroom')->count(), 'history preserved, not deleted');
    }

    // -------------------------------------------------- authority transfer

    /**
     * LOAD-BEARING. The Communication half of this same scenario lives in
     * CommunicationAudienceTest (the inverted stale-handover regression);
     * this is the Attendance half, since both surfaces must close together.
     */
    public function test_attendance_access_transfers_from_outgoing_to_incoming_homeroom_teacher_on_handover(): void
    {
        $class = $this->class('Year 5', 'Year 5A');
        $sarah = $this->teacherStaff('sarah');
        $eka = $this->teacherStaff('eka');
        $this->assignHomeroom($class, $sarah);

        $sarahUser = $sarah->user->fresh();
        $ekaUser = $eka->user->fresh();
        $this->assertTrue($sarahUser->can('recordFor', [Attendance::class, $class]));
        $this->assertFalse($ekaUser->can('recordFor', [Attendance::class, $class]));

        $this->handoverHomeroom($class, $eka);

        $this->assertFalse($sarahUser->fresh()->can('recordFor', [Attendance::class, $class]), 'outgoing teacher loses attendance access');
        $this->assertTrue($ekaUser->fresh()->can('recordFor', [Attendance::class, $class]), 'incoming teacher gains attendance access');
    }

    // ----------------------------------------------------------- resolver

    public function test_homeroom_teacher_resolver_returns_null_when_none_is_assigned(): void
    {
        $class = $this->class('Year 5', 'Year 5A');

        $this->assertNull($class->homeroomTeacher());
    }

    public function test_homeroom_teacher_resolver_returns_the_current_staff(): void
    {
        $class = $this->class('Year 5', 'Year 5A');
        $sarah = $this->teacherStaff('sarah');
        $this->assignHomeroom($class, $sarah);

        $this->assertTrue($class->fresh()->homeroomTeacher()->is($sarah));
    }

    public function test_homeroom_teacher_resolver_ignores_historical_rows_and_returns_the_current_one(): void
    {
        $class = $this->class('Year 5', 'Year 5A');
        $sarah = $this->teacherStaff('sarah');
        $eka = $this->teacherStaff('eka');
        $this->assignHomeroom($class, $sarah);
        $this->handoverHomeroom($class, $eka);

        // Must never arbitrarily return the historical first row.
        $this->assertTrue($class->fresh()->homeroomTeacher()->is($eka));
    }

    public function test_homeroom_teacher_resolver_fails_loud_if_more_than_one_row_is_open(): void
    {
        // Same defense-in-depth reasoning as AcademicYear::current()
        // (Foundation F1): the DB constraint makes this state unreachable
        // through any normal write path, so the index is dropped here to
        // simulate the corruption the resolver's guard exists for.
        DB::statement('DROP INDEX class_teacher_homeroom_open_unique');
        $class = $this->class('Year 5', 'Year 5A');
        $sarah = $this->teacherStaff('sarah');
        $eka = $this->teacherStaff('eka');
        DB::table('class_teacher')->insert(['class_id' => $class->id, 'staff_id' => $sarah->id, 'role' => 'homeroom', 'started_on' => today()]);
        DB::table('class_teacher')->insert(['class_id' => $class->id, 'staff_id' => $eka->id, 'role' => 'homeroom', 'started_on' => today()]);

        $this->expectException(\LogicException::class);

        $class->fresh()->homeroomTeacher();
    }

    // ----------------------------------------------- subject_teacher deprecation

    public function test_class_teacher_service_has_no_write_path_for_subject_teacher(): void
    {
        // Structural, not a runtime guard: ClassTeacherService exposes only
        // setHomeroom()/assignAssistant()/endAssignment()/endHomeroom(),
        // none of which accept an arbitrary role, so 'subject_teacher' has
        // no path to a new row through the one write path this codebase has.
        $methods = array_map(
            fn (\ReflectionMethod $m) => $m->getName(),
            (new \ReflectionClass(ClassTeacherService::class))->getMethods(\ReflectionMethod::IS_PUBLIC)
        );

        $this->assertEqualsCanonicalizing(['setHomeroom', 'endHomeroom', 'assignAssistant', 'endAssignment'], $methods);
    }

    public function test_a_legacy_subject_teacher_row_is_preserved_and_readable(): void
    {
        $class = $this->class('Year 5', 'Year 5A');
        $sarah = $this->teacherStaff('sarah');
        DB::table('class_teacher')->insert([
            'class_id' => $class->id, 'staff_id' => $sarah->id, 'role' => 'subject_teacher', 'started_on' => today(),
        ]);

        $this->assertDatabaseHas('class_teacher', ['class_id' => $class->id, 'role' => 'subject_teacher']);
        // Not authoritative: the homeroom resolver ignores it entirely.
        $this->assertNull($class->fresh()->homeroomTeacher());
    }

    // ---------------------------------------------------------- date range

    public function test_ending_an_assignment_before_its_start_date_is_refused(): void
    {
        $class = $this->class('Year 5', 'Year 5A');
        $sarah = $this->teacherStaff('sarah');
        $assignment = $this->service()->setHomeroom($class, $sarah, today());

        $this->expectException(ValidationException::class);

        $this->service()->endAssignment($assignment, today()->subDay());
    }

    public function test_ending_an_already_closed_assignment_is_refused(): void
    {
        $class = $this->class('Year 5', 'Year 5A');
        $sarah = $this->teacherStaff('sarah');
        $assignment = $this->service()->setHomeroom($class, $sarah);
        $this->service()->endAssignment($assignment);

        $this->expectException(ValidationException::class);

        $this->service()->endAssignment($assignment->fresh());
    }

    // ------------------------------------------------------ AcademicRecord

    public function test_academic_record_homeroom_resolution_ignores_a_closed_historical_row(): void
    {
        // Direct proof of the whereNull('ended_on') fix: a closed row
        // coexisting with an open one must not trigger a false-positive
        // "more than one homeroom teacher" refusal.
        $class = $this->class('Year 5', 'Year 5A');
        $sarah = $this->teacherStaff('sarah');
        $eka = $this->teacherStaff('eka');
        $this->assignHomeroom($class, $sarah);
        $this->handoverHomeroom($class, $eka);

        $name = app(AcademicRecordService::class)->resolveHomeroomTeacherName($class->fresh());

        $this->assertSame($eka->fullName(), $name);
    }
}
