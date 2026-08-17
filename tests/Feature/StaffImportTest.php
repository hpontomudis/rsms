<?php

namespace Tests\Feature;

use App\Models\Position;
use App\Models\Staff;
use App\Models\StaffCategory;
use App\Models\User;
use App\Services\Import\StaffImportService;
use App\Services\Import\StaffImportValidator;
use App\Services\Import\StaffTemplateBuilder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class StaffImportTest extends TestCase
{
    use RefreshDatabase;

    /** Builds a real temp .xlsx file from row arrays, matching StaffTemplateBuilder::HEADINGS order. */
    private function xlsxFile(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Staff');
        $sheet->fromArray(StaffTemplateBuilder::HEADINGS, null, 'A1');
        $r = 2;
        foreach ($rows as $row) {
            $sheet->fromArray($row, null, "A{$r}");
            $r++;
        }

        $path = tempnam(sys_get_temp_dir(), 'staff_import_test').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function validRow(array $overrides = []): array
    {
        $defaults = [
            'staff_number' => 'IMP-'.uniqid(),
            'first_name' => 'Imported', 'last_name' => 'Person',
            'position' => 'Support Staff', 'staff_category' => '',
            'phone' => '0812-0000-0000', 'email' => 'imported-'.uniqid().'@rahai.sch.id',
            'nik' => '', 'hire_date' => '2024-07-01', 'status' => 'active',
            'create_login' => 'no', 'role' => '',
        ];
        $merged = array_merge($defaults, $overrides);

        return array_values(array_replace(array_flip(StaffTemplateBuilder::HEADINGS), $merged));
    }

    private function actingAsAdminStaff(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin_staff');
        $this->actingAs($user);

        return $user;
    }

    public function test_template_download_is_accessible_and_returns_a_spreadsheet(): void
    {
        $this->actingAsAdminStaff();

        Livewire::test(\App\Livewire\Staff\Import::class)
            ->call('downloadTemplate')
            ->assertStatus(200);
    }

    public function test_a_valid_row_imports_successfully(): void
    {
        $this->actingAsAdminStaff();
        $path = $this->xlsxFile([$this->validRow(['staff_number' => 'IMP-VALID-1'])]);

        $rows = app(StaffImportValidator::class)->validate($path);
        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]->isValid(), implode(', ', $rows[0]->errors));

        $result = app(StaffImportService::class)->import($rows);
        $this->assertCount(1, $result);
        $this->assertDatabaseHas('staff', ['staff_number' => 'IMP-VALID-1']);
    }

    public function test_an_invalid_nik_is_reported_with_a_row_specific_error(): void
    {
        $this->actingAsAdminStaff();
        $path = $this->xlsxFile([$this->validRow(['staff_number' => 'IMP-BADNIK', 'nik' => '12345'])]);

        $rows = app(StaffImportValidator::class)->validate($path);

        $this->assertFalse($rows[0]->isValid());
        $this->assertStringContainsString('nik', strtolower(implode(' ', $rows[0]->errors)));
    }

    public function test_a_duplicate_email_against_an_existing_staff_member_is_rejected(): void
    {
        $this->actingAsAdminStaff();
        Staff::create([
            'staff_number' => 'EXISTING-1', 'first_name' => 'A', 'last_name' => 'B',
            'position_id' => Position::firstOrCreate(['title' => 'Support Staff'])->id,
            'phone' => '0811', 'email' => 'already-taken@rahai.sch.id', 'hire_date' => '2020-01-01',
        ]);
        $path = $this->xlsxFile([$this->validRow(['staff_number' => 'IMP-DUPE', 'email' => 'already-taken@rahai.sch.id'])]);

        $rows = app(StaffImportValidator::class)->validate($path);

        $this->assertFalse($rows[0]->isValid());
        $this->assertStringContainsString('already belongs', implode(' ', $rows[0]->errors));
    }

    public function test_an_unknown_staff_category_is_reported(): void
    {
        $this->actingAsAdminStaff();
        $path = $this->xlsxFile([$this->validRow(['staff_number' => 'IMP-CAT', 'staff_category' => 'Nonexistent Category'])]);

        $rows = app(StaffImportValidator::class)->validate($path);

        $this->assertFalse($rows[0]->isValid());
        $this->assertStringContainsString('Unknown Staff Category', implode(' ', $rows[0]->errors));
    }

    public function test_an_unknown_role_when_creating_a_login_is_reported(): void
    {
        $this->actingAsAdminStaff();
        $path = $this->xlsxFile([$this->validRow([
            'staff_number' => 'IMP-ROLE', 'create_login' => 'yes', 'role' => 'not_a_real_role',
        ])]);

        $rows = app(StaffImportValidator::class)->validate($path);

        $this->assertFalse($rows[0]->isValid());
        $this->assertStringContainsString('not permitted', implode(' ', $rows[0]->errors));
    }

    public function test_super_admin_cannot_be_assigned_through_bulk_import(): void
    {
        $this->actingAsAdminStaff();
        $path = $this->xlsxFile([$this->validRow([
            'staff_number' => 'IMP-ESCALATE', 'create_login' => 'yes', 'role' => 'super_admin',
        ])]);

        $rows = app(StaffImportValidator::class)->validate($path);

        $this->assertFalse($rows[0]->isValid());
        $this->assertNotContains('super_admin', StaffImportValidator::ALLOWED_ROLES);
    }

    public function test_account_provisioning_creates_a_login_with_a_temporary_password(): void
    {
        $this->actingAsAdminStaff();
        $path = $this->xlsxFile([$this->validRow([
            'staff_number' => 'IMP-LOGIN', 'email' => 'imp-login@rahai.sch.id', 'create_login' => 'yes', 'role' => 'teacher',
        ])]);

        $rows = app(StaffImportValidator::class)->validate($path);
        $this->assertTrue($rows[0]->isValid(), implode(', ', $rows[0]->errors));

        $result = app(StaffImportService::class)->import($rows);

        $this->assertNotNull($result[0]['credential']);
        $this->assertNotEmpty($result[0]['credential']['password']);
        $staff = $result[0]['staff']->fresh();
        $this->assertNotNull($staff->user_id);
        $this->assertTrue($staff->user->hasRole('teacher'));
        $this->assertTrue($staff->user->must_change_password);
    }

    public function test_import_service_refuses_a_batch_containing_any_invalid_row(): void
    {
        $this->actingAsAdminStaff();
        $invalidRow = new \App\Services\Import\StaffImportRow(2, ['staff_number' => 'X'], ['some error']);

        $this->expectException(\InvalidArgumentException::class);
        app(StaffImportService::class)->import([$invalidRow]);
    }

    public function test_import_is_denied_to_a_teacher(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $this->assertFalse($teacher->can('import', Staff::class));
    }

    public function test_two_rows_in_the_same_file_with_the_same_email_are_both_flagged(): void
    {
        $this->actingAsAdminStaff();
        $path = $this->xlsxFile([
            $this->validRow(['staff_number' => 'IMP-D1', 'email' => 'same@rahai.sch.id']),
            $this->validRow(['staff_number' => 'IMP-D2', 'email' => 'same@rahai.sch.id']),
        ]);

        $rows = app(StaffImportValidator::class)->validate($path);

        $this->assertTrue($rows[0]->isValid());
        $this->assertFalse($rows[1]->isValid());
    }
}
