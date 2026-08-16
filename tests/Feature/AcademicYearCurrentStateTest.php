<?php

namespace Tests\Feature;

use App\Livewire\Classes\Index as ClassesIndex;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\AcademicYearService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Foundation F1 -- AcademicYear current-state integrity. Covers the
 * database invariant (academic_years_current_unique), the fail-loud
 * resolver, AcademicYearService::setCurrent() as the one canonical write
 * path, the seeder's use of it, the minimal admin UI's authorization
 * boundary, and the "current-year switch changes defaults only" guarantee.
 */
class AcademicYearCurrentStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    // ------------------------------------------------------------- schema

    public function test_the_database_rejects_a_second_current_academic_year(): void
    {
        $this->year('2026/2027', current: true);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $this->year('2027/2028', current: true);
    }

    // ----------------------------------------------------------- resolver

    public function test_the_resolver_returns_the_single_current_year(): void
    {
        $other = $this->year('2025/2026', current: false);
        $current = $this->year('2026/2027', current: true);

        $this->assertTrue(AcademicYear::current()->is($current));
        $this->assertFalse(AcademicYear::current()->is($other));
    }

    public function test_the_resolver_returns_null_when_no_year_is_current(): void
    {
        $this->year('2026/2027', current: false);

        $this->assertNull(AcademicYear::current());
    }

    public function test_the_resolver_fails_loud_if_more_than_one_row_is_flagged_current(): void
    {
        // The DB constraint already prevents this through any normal write
        // path (see the schema test above). This proves the resolver
        // itself never silently picks one even if that constraint were
        // ever bypassed -- "DB enforces at most one, service enforces
        // exactly one" applies to the read side too.
        DB::statement('DROP INDEX academic_years_current_unique');

        $this->year('2026/2027', current: true);
        $this->year('2027/2028', current: true);

        $this->expectException(\LogicException::class);

        AcademicYear::current();
    }

    // --------------------------------------------------------- setCurrent

    public function test_set_current_leaves_exactly_one_current_row(): void
    {
        $old = $this->year('2026/2027', current: true);
        $new = $this->year('2027/2028', current: false);

        app(AcademicYearService::class)->setCurrent($new);

        $this->assertSame(1, AcademicYear::where('is_current', true)->count());
        $this->assertFalse($old->fresh()->is_current);
        $this->assertTrue($new->fresh()->is_current);
    }

    public function test_set_current_on_the_already_current_year_is_idempotent(): void
    {
        $year = $this->year('2026/2027', current: true);
        $auditCountBefore = AuditLog::where('auditable_type', AcademicYear::class)
            ->where('auditable_id', $year->id)
            ->count();

        app(AcademicYearService::class)->setCurrent($year);
        app(AcademicYearService::class)->setCurrent($year);

        $this->assertSame(1, AcademicYear::where('is_current', true)->count());
        $this->assertTrue($year->fresh()->is_current);
        // No-op writes must not produce audit noise -- Auditable's updated
        // hook only fires when an attribute actually changes.
        $this->assertSame(
            $auditCountBefore,
            AuditLog::where('auditable_type', AcademicYear::class)->where('auditable_id', $year->id)->count()
        );
    }

    // -------------------------------------------------------------- audit

    public function test_switching_current_academic_year_writes_an_audit_log_entry(): void
    {
        $old = $this->year('2026/2027', current: true);
        $new = $this->year('2027/2028', current: false);

        app(AcademicYearService::class)->setCurrent($new);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => AcademicYear::class,
            'auditable_id' => $new->id,
            'action' => 'updated',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => AcademicYear::class,
            'auditable_id' => $old->id,
            'action' => 'updated',
        ]);
    }

    // -------------------------------------------------------------- seeder

    public function test_seeding_produces_exactly_one_current_academic_year(): void
    {
        $this->seed(\Database\Seeders\AcademicYearSeeder::class);

        $this->assertSame(1, AcademicYear::where('is_current', true)->count());
    }

    public function test_re_running_the_seeder_remains_idempotent_and_correct(): void
    {
        $this->seed(\Database\Seeders\AcademicYearSeeder::class);
        $firstRunCount = AcademicYear::count();

        $this->seed(\Database\Seeders\AcademicYearSeeder::class);

        $this->assertSame($firstRunCount, AcademicYear::count());
        $this->assertSame(1, AcademicYear::where('is_current', true)->count());
    }

    // ---------------------------------------------------------- admin UI

    public function test_an_authorized_management_user_can_switch_the_current_academic_year(): void
    {
        $old = $this->year('2026/2027', current: true);
        $new = $this->year('2027/2028', current: false);
        $adminStaff = User::factory()->create();
        $adminStaff->assignRole('admin_staff');

        Livewire::actingAs($adminStaff)
            ->test(ClassesIndex::class)
            ->set('switch_academic_year_id', (string) $new->id)
            ->call('switchCurrentAcademicYear')
            ->assertHasNoErrors();

        $this->assertTrue($new->fresh()->is_current);
        $this->assertFalse($old->fresh()->is_current);
    }

    public function test_an_unauthorized_user_cannot_switch_the_current_academic_year(): void
    {
        $old = $this->year('2026/2027', current: true);
        $new = $this->year('2027/2028', current: false);
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        Livewire::actingAs($teacher)
            ->test(ClassesIndex::class)
            ->set('switch_academic_year_id', (string) $new->id)
            ->call('switchCurrentAcademicYear')
            ->assertForbidden();

        $this->assertTrue($old->fresh()->is_current);
        $this->assertFalse($new->fresh()->is_current);
    }

    public function test_the_switcher_is_not_rendered_for_a_user_without_the_permission(): void
    {
        $this->year('2026/2027', current: true);
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        Livewire::actingAs($teacher)
            ->test(ClassesIndex::class)
            ->assertDontSee('Change current Academic Year');
    }

    // ------------------------------------------------------------ history

    public function test_switching_current_academic_year_does_not_modify_unrelated_data(): void
    {
        $old = $this->year('2026/2027', current: true);
        $new = $this->year('2027/2028', current: false);
        $grade = Grade::create(['name' => 'Grade 5', 'level_order' => 5]);
        $class = SchoolClass::create(['name' => 'Grade 5A', 'grade_id' => $grade->id, 'academic_year_id' => $old->id, 'capacity' => 30]);
        $student = Student::create([
            'student_number' => 'STU-F1-001',
            'first_name' => 'Fitri',
            'last_name' => 'Wati',
            'date_of_birth' => '2015-01-01',
            'gender' => 'female',
            'enrollment_date' => '2026-07-01',
            'status' => 'active',
        ]);
        $class->students()->attach($student->id, ['enrolled_at' => '2026-07-01', 'status' => 'active']);

        app(AcademicYearService::class)->setCurrent($new);

        $this->assertSame(1, SchoolClass::count());
        $this->assertSame($old->id, $class->fresh()->academic_year_id);
        $this->assertSame('active', $class->fresh()->students()->first()->pivot->status);
        $this->assertSame(1, Student::count());
    }

    // -------------------------------------------------- ManagementInsights

    public function test_no_management_insight_class_calls_academic_year_current_internally(): void
    {
        // Docblock mentions of AcademicYear::current() are expected -- several
        // files explicitly document that providers MUST NOT call it. Only an
        // actual call, outside comments, is a violation.
        $offenders = collect(File::allFiles(app_path('ManagementInsights')))
            ->filter(function ($file) {
                $code = File::get($file->getPathname());
                $code = preg_replace('#/\*.*?\*/#s', '', $code);
                $code = preg_replace('#//[^\n]*#', '', $code);

                return str_contains($code, 'AcademicYear::current(');
            })
            ->map(fn ($file) => $file->getRelativePathname());

        $this->assertTrue(
            $offenders->isEmpty(),
            'Unexpected AcademicYear::current() call in: '.$offenders->implode(', ')
        );
    }

    // ------------------------------------------------------------ helpers

    private function year(string $name, bool $current): AcademicYear
    {
        [$start] = explode('/', $name);

        return AcademicYear::create([
            'name' => $name,
            'start_date' => "{$start}-07-01",
            'end_date' => ((int) $start + 1).'-06-30',
            'is_current' => $current,
        ]);
    }
}
