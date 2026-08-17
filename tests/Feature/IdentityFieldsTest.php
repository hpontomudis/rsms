<?php

namespace Tests\Feature;

use App\Models\Position;
use App\Models\Staff;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IdentityFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function staffAttributes(array $overrides = []): array
    {
        return array_merge([
            'staff_number' => 'STF-'.uniqid(),
            'first_name' => 'Test', 'last_name' => 'Staff',
            'position_id' => Position::firstOrCreate(['title' => 'Support Staff'])->id,
            'phone' => '0812-0000-0000',
            'hire_date' => '2020-07-01',
        ], $overrides);
    }

    private function studentAttributes(array $overrides = []): array
    {
        return array_merge([
            'student_number' => 'STU-'.uniqid(),
            'first_name' => 'Test', 'last_name' => 'Student',
            'date_of_birth' => '2015-03-12', 'gender' => 'male',
            'enrollment_date' => '2026-07-01',
        ], $overrides);
    }

    // --- NIK ---

    public function test_staff_accepts_a_valid_16_digit_nik(): void
    {
        $staff = Staff::create($this->staffAttributes(['nik' => '3271010101990001']));
        $this->assertNotNull($staff->fresh()->nik);
        $this->assertSame(16, strlen($staff->fresh()->nik));
    }

    public function test_student_accepts_a_valid_16_digit_nik(): void
    {
        $student = Student::create($this->studentAttributes(['nik' => '3271010101150001']));
        $this->assertSame('3271010101150001', $student->fresh()->nik);
    }

    public function test_staff_nik_leading_zero_is_preserved_as_a_string(): void
    {
        $staff = Staff::create($this->staffAttributes(['nik' => '0271010101990001']));
        $this->assertSame('0271010101990001', $staff->fresh()->nik);
    }

    public function test_staff_nik_null_is_allowed(): void
    {
        $staff = Staff::create($this->staffAttributes(['nik' => null]));
        $this->assertNull($staff->fresh()->nik);
    }

    public function test_student_nik_null_is_allowed(): void
    {
        $student = Student::create($this->studentAttributes(['nik' => null]));
        $this->assertNull($student->fresh()->nik);
    }

    public function test_staff_duplicate_non_null_nik_is_rejected_at_the_database_level(): void
    {
        Staff::create($this->staffAttributes(['nik' => '3271010101990001']));

        $this->expectException(\Illuminate\Database\QueryException::class);
        Staff::create($this->staffAttributes(['nik' => '3271010101990001']));
    }

    public function test_student_duplicate_non_null_nik_is_rejected_at_the_database_level(): void
    {
        Student::create($this->studentAttributes(['nik' => '3271010101150001']));

        $this->expectException(\Illuminate\Database\QueryException::class);
        Student::create($this->studentAttributes(['nik' => '3271010101150001']));
    }

    public function test_two_staff_may_both_have_a_null_nik(): void
    {
        Staff::create($this->staffAttributes(['nik' => null]));
        $second = Staff::create($this->staffAttributes(['nik' => null]));

        $this->assertNotNull($second->id);
    }

    public function test_staff_nik_uniqueness_is_enforced_regardless_of_database_driver(): void
    {
        // Plain nullable unique() -- not the partial-index technique used
        // elsewhere in this codebase -- so this must hold identically on
        // both drivers, not just PostgreSQL.
        $this->assertContains(DB::connection()->getDriverName(), ['sqlite', 'pgsql']);

        Staff::create($this->staffAttributes(['nik' => '3271010101990099']));
        try {
            Staff::create($this->staffAttributes(['nik' => '3271010101990099']));
            $this->fail('Expected a unique-constraint violation.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertTrue(true);
        }
    }

    // --- NIK validation via Livewire Create components ---

    public function test_staff_create_rejects_a_nik_with_the_wrong_length(): void
    {
        $this->seedRoles();
        $admin = \App\Models\User::factory()->create();
        $admin->assignRole('super_admin');

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Livewire\Staff\Create::class)
            ->set('staff_number', 'STF-X1')
            ->set('first_name', 'A')->set('last_name', 'B')
            ->set('position_title', 'Support Staff')
            ->set('phone', '0812')
            ->set('hire_date', '2020-07-01')
            ->set('nik', '12345')
            ->call('save')
            ->assertHasErrors(['nik']);
    }

    public function test_staff_create_rejects_a_nik_containing_letters(): void
    {
        $this->seedRoles();
        $admin = \App\Models\User::factory()->create();
        $admin->assignRole('super_admin');

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Livewire\Staff\Create::class)
            ->set('staff_number', 'STF-X2')
            ->set('first_name', 'A')->set('last_name', 'B')
            ->set('position_title', 'Support Staff')
            ->set('phone', '0812')
            ->set('hire_date', '2020-07-01')
            ->set('nik', '327101010199000A')
            ->call('save')
            ->assertHasErrors(['nik']);
    }

    // --- NISN ---

    public function test_student_accepts_a_valid_10_digit_nisn(): void
    {
        $student = Student::create($this->studentAttributes(['nisn' => '0012345678']));
        $this->assertSame('0012345678', $student->fresh()->nisn);
    }

    public function test_student_nisn_null_is_allowed(): void
    {
        $student = Student::create($this->studentAttributes(['nisn' => null]));
        $this->assertNull($student->fresh()->nisn);
    }

    public function test_student_duplicate_non_null_nisn_is_rejected_at_the_database_level(): void
    {
        Student::create($this->studentAttributes(['nisn' => '0012345678']));

        $this->expectException(\Illuminate\Database\QueryException::class);
        Student::create($this->studentAttributes(['nisn' => '0012345678']));
    }

    public function test_student_create_rejects_a_nisn_with_the_wrong_length(): void
    {
        $this->seedRoles();
        $admin = \App\Models\User::factory()->create();
        $admin->assignRole('super_admin');

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Livewire\Students\Create::class)
            ->set('student_number', 'STU-X1')
            ->set('first_name', 'A')->set('last_name', 'B')
            ->set('date_of_birth', '2015-01-01')
            ->set('gender', 'male')
            ->set('enrollment_date', '2026-07-01')
            ->set('nisn', '12345')
            ->call('save')
            ->assertHasErrors(['nisn']);
    }

    public function test_student_create_rejects_a_nisn_containing_letters(): void
    {
        $this->seedRoles();
        $admin = \App\Models\User::factory()->create();
        $admin->assignRole('super_admin');

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Livewire\Students\Create::class)
            ->set('student_number', 'STU-X2')
            ->set('first_name', 'A')->set('last_name', 'B')
            ->set('date_of_birth', '2015-01-01')
            ->set('gender', 'male')
            ->set('enrollment_date', '2026-07-01')
            ->set('nisn', '00123ABCDE')
            ->call('save')
            ->assertHasErrors(['nisn']);
    }

    public function test_leaving_nik_and_nisn_blank_saves_successfully_through_the_ui(): void
    {
        $this->seedRoles();
        $admin = \App\Models\User::factory()->create();
        $admin->assignRole('super_admin');

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Livewire\Students\Create::class)
            ->set('student_number', 'STU-X3')
            ->set('first_name', 'A')->set('last_name', 'B')
            ->set('date_of_birth', '2015-01-01')
            ->set('gender', 'male')
            ->set('enrollment_date', '2026-07-01')
            ->call('save')
            ->assertHasNoErrors(['nik', 'nisn']);

        $this->assertDatabaseHas('students', ['student_number' => 'STU-X3', 'nik' => null, 'nisn' => null]);
    }

    public function test_two_students_can_both_leave_nik_and_nisn_blank_through_the_ui(): void
    {
        $this->seedRoles();
        $admin = \App\Models\User::factory()->create();
        $admin->assignRole('super_admin');

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Livewire\Students\Create::class)
            ->set('student_number', 'STU-Y1')->set('first_name', 'A')->set('last_name', 'B')
            ->set('date_of_birth', '2015-01-01')->set('gender', 'male')->set('enrollment_date', '2026-07-01')
            ->call('save');

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Livewire\Students\Create::class)
            ->set('student_number', 'STU-Y2')->set('first_name', 'C')->set('last_name', 'D')
            ->set('date_of_birth', '2015-01-01')->set('gender', 'male')->set('enrollment_date', '2026-07-01')
            ->call('save')
            ->assertHasNoErrors();
    }

    // --- Audit ---

    public function test_changing_staff_nik_produces_an_audit_row(): void
    {
        $staff = Staff::create($this->staffAttributes(['nik' => '3271010101990002']));
        $staff->update(['nik' => '3271010101990003']);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Staff::class,
            'auditable_id' => $staff->id,
            'action' => 'updated',
        ]);
    }

    private function seedRoles(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
}
