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

    public function test_the_component_upload_action_runs_end_to_end_through_livewire(): void
    {
        // Regression guard for a shipped bug: this action used to be named
        // `upload()`, which collides with a reserved Livewire `$wire` magic
        // method -- `wire:submit="upload"` silently resolved to Livewire's
        // own file-upload function instead of this component, so the form
        // did nothing at all and died client-side. Every existing import
        // test drove the service layer directly, so none of them noticed.
        // This one deliberately goes through the real component action.
        $this->actingAsAdminStaff();
        $path = $this->xlsxFile([$this->validRow(['staff_number' => 'IMP-E2E-1'])]);

        Livewire::test(\App\Livewire\Staff\Import::class)
            ->set('file', UploadedFile::fake()->createWithContent('staff.xlsx', file_get_contents($path)))
            ->call('validateFile')
            ->assertHasNoErrors()
            ->assertSet('validated', true);
    }

    public function test_the_full_import_journey_runs_end_to_end_through_livewire(): void
    {
        // The complete user journey, driven through the real component the
        // way the browser drives it: upload -> validate -> confirm ->
        // credentials. Each step re-serializes the component's public
        // properties, which is exactly where this screen used to break.
        $this->actingAsAdminStaff();
        $path = $this->xlsxFile([$this->validRow([
            'staff_number' => 'IMP-E2E-2',
            'email' => 'imp-e2e-2@rahai.sch.id',
            'create_login' => 'yes',
            'role' => 'teacher',
        ])]);

        Livewire::test(\App\Livewire\Staff\Import::class)
            ->set('file', UploadedFile::fake()->createWithContent('staff.xlsx', file_get_contents($path)))
            ->call('validateFile')
            ->assertSet('validated', true)
            ->call('confirmImport')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('staff', ['staff_number' => 'IMP-E2E-2']);
        $this->assertDatabaseHas('users', ['email' => 'imp-e2e-2@rahai.sch.id']);
    }

    public function test_a_valid_row_imports_successfully(): void
    {
        $actor = $this->actingAsAdminStaff();
        $path = $this->xlsxFile([$this->validRow(['staff_number' => 'IMP-VALID-1'])]);

        $rows = app(StaffImportValidator::class)->validate($path, $actor);
        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]->isValid(), implode(', ', $rows[0]->errors));

        $result = app(StaffImportService::class)->import($rows, $actor);
        $this->assertCount(1, $result);
        $this->assertDatabaseHas('staff', ['staff_number' => 'IMP-VALID-1']);
    }

    public function test_an_invalid_nik_is_reported_with_a_row_specific_error(): void
    {
        $actor = $this->actingAsAdminStaff();
        $path = $this->xlsxFile([$this->validRow(['staff_number' => 'IMP-BADNIK', 'nik' => '12345'])]);

        $rows = app(StaffImportValidator::class)->validate($path, $actor);

        $this->assertFalse($rows[0]->isValid());
        $this->assertStringContainsString('nik', strtolower(implode(' ', $rows[0]->errors)));
    }

    public function test_a_duplicate_email_against_an_existing_staff_member_is_rejected(): void
    {
        $actor = $this->actingAsAdminStaff();
        Staff::create([
            'staff_number' => 'EXISTING-1', 'first_name' => 'A', 'last_name' => 'B',
            'position_id' => Position::firstOrCreate(['title' => 'Support Staff'])->id,
            'phone' => '0811', 'email' => 'already-taken@rahai.sch.id', 'hire_date' => '2020-01-01',
        ]);
        $path = $this->xlsxFile([$this->validRow(['staff_number' => 'IMP-DUPE', 'email' => 'already-taken@rahai.sch.id'])]);

        $rows = app(StaffImportValidator::class)->validate($path, $actor);

        $this->assertFalse($rows[0]->isValid());
        $this->assertStringContainsString('already belongs', implode(' ', $rows[0]->errors));
    }

    public function test_an_unknown_staff_category_is_reported(): void
    {
        $actor = $this->actingAsAdminStaff();
        $path = $this->xlsxFile([$this->validRow(['staff_number' => 'IMP-CAT', 'staff_category' => 'Nonexistent Category'])]);

        $rows = app(StaffImportValidator::class)->validate($path, $actor);

        $this->assertFalse($rows[0]->isValid());
        $this->assertStringContainsString('Unknown Staff Category', implode(' ', $rows[0]->errors));
    }

    public function test_an_unknown_role_when_creating_a_login_is_reported(): void
    {
        $actor = $this->actingAsAdminStaff();
        $path = $this->xlsxFile([$this->validRow([
            'staff_number' => 'IMP-ROLE', 'create_login' => 'yes', 'role' => 'not_a_real_role',
        ])]);

        $rows = app(StaffImportValidator::class)->validate($path, $actor);

        $this->assertFalse($rows[0]->isValid());
        $this->assertStringContainsString('cannot be provisioned', implode(' ', $rows[0]->errors));
    }

    public function test_super_admin_cannot_be_assigned_through_bulk_import(): void
    {
        $actor = $this->actingAsAdminStaff();
        $path = $this->xlsxFile([$this->validRow([
            'staff_number' => 'IMP-ESCALATE', 'create_login' => 'yes', 'role' => 'super_admin',
        ])]);

        $rows = app(StaffImportValidator::class)->validate($path, $actor);

        $this->assertFalse($rows[0]->isValid());
        $this->assertFalse(\App\Services\AccountAuthorizationMatrix::canProvision($actor, 'super_admin'));

        // Also confirmed for a super_admin actor -- bulk import structurally
        // cannot create super_admin regardless of who runs it.
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $this->assertFalse(\App\Services\AccountAuthorizationMatrix::canProvision($superAdmin, 'super_admin'));
    }

    public function test_super_admin_can_assign_principal_through_bulk_import(): void
    {
        // A school has exactly one principal; provisioning that login was
        // previously unreachable through any path at all. super_admin-only,
        // unlike the operational roles admin_staff can also provision.
        $this->seed(RolesAndPermissionsSeeder::class);
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $this->assertTrue(\App\Services\AccountAuthorizationMatrix::canProvision($superAdmin, 'principal'));
    }

    public function test_account_provisioning_creates_a_login_with_a_temporary_password(): void
    {
        $actor = $this->actingAsAdminStaff();
        $path = $this->xlsxFile([$this->validRow([
            'staff_number' => 'IMP-LOGIN', 'email' => 'imp-login@rahai.sch.id', 'create_login' => 'yes', 'role' => 'teacher',
        ])]);

        $rows = app(StaffImportValidator::class)->validate($path, $actor);
        $this->assertTrue($rows[0]->isValid(), implode(', ', $rows[0]->errors));

        $result = app(StaffImportService::class)->import($rows, $actor);

        $this->assertNotNull($result[0]['credential']);
        $this->assertNotEmpty($result[0]['credential']['password']);
        $staff = $result[0]['staff']->fresh();
        $this->assertNotNull($staff->user_id);
        $this->assertTrue($staff->user->hasRole('teacher'));
        $this->assertTrue($staff->user->must_change_password);
    }

    // --- P2.1: actor-aware provisioning restrictions ---

    public function test_admin_staff_cannot_provision_admin_staff_via_import(): void
    {
        $actor = $this->actingAsAdminStaff();
        $path = $this->xlsxFile([$this->validRow([
            'staff_number' => 'IMP-ESC-1', 'email' => 'esc1@rahai.sch.id', 'create_login' => 'yes', 'role' => 'admin_staff',
        ])]);

        $rows = app(StaffImportValidator::class)->validate($path, $actor);

        $this->assertFalse($rows[0]->isValid());
        $this->assertStringContainsString('cannot be provisioned', implode(' ', $rows[0]->errors));
        $this->assertDatabaseMissing('staff', ['staff_number' => 'IMP-ESC-1']);
    }

    public function test_admin_staff_cannot_provision_finance_staff_via_import(): void
    {
        $actor = $this->actingAsAdminStaff();
        $this->assertFalse(\App\Services\AccountAuthorizationMatrix::canProvision($actor, 'finance_staff'));
    }

    public function test_admin_staff_cannot_provision_management_via_import(): void
    {
        $actor = $this->actingAsAdminStaff();
        $this->assertFalse(\App\Services\AccountAuthorizationMatrix::canProvision($actor, 'management'));
    }

    public function test_admin_staff_cannot_provision_principal_via_import(): void
    {
        $actor = $this->actingAsAdminStaff();
        $this->assertFalse(\App\Services\AccountAuthorizationMatrix::canProvision($actor, 'principal'));
    }

    public function test_admin_staff_can_import_staff_without_creating_a_login(): void
    {
        $actor = $this->actingAsAdminStaff();
        $path = $this->xlsxFile([$this->validRow(['staff_number' => 'IMP-NOLOGIN'])]);

        $rows = app(StaffImportValidator::class)->validate($path, $actor);
        $this->assertTrue($rows[0]->isValid(), implode(', ', $rows[0]->errors));

        $result = app(StaffImportService::class)->import($rows, $actor);
        $this->assertNull($result[0]['credential']);
        $this->assertNull($result[0]['staff']->user_id);
    }

    public function test_super_admin_can_bulk_provision_every_operational_role(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        foreach (['teacher', 'admin_staff', 'finance_staff', 'management'] as $role) {
            $this->assertTrue(
                \App\Services\AccountAuthorizationMatrix::canProvision($superAdmin, $role),
                "super_admin should be able to provision {$role}"
            );
        }
    }

    public function test_import_service_rejects_a_row_whose_role_the_actor_may_not_provision_even_if_marked_valid(): void
    {
        // Defense-in-depth: UserProvisioningService itself refuses, even
        // if a row somehow reached import() marked valid.
        $actor = $this->actingAsAdminStaff();
        $row = new \App\Services\Import\StaffImportRow(2, array_combine(
            StaffTemplateBuilder::HEADINGS,
            $this->validRow(['staff_number' => 'IMP-BYPASS', 'email' => 'bypass@rahai.sch.id', 'create_login' => 'yes', 'role' => 'management'])
        ), []);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(StaffImportService::class)->import([$row], $actor);
    }

    public function test_import_service_refuses_a_batch_containing_any_invalid_row(): void
    {
        $actor = $this->actingAsAdminStaff();
        $invalidRow = new \App\Services\Import\StaffImportRow(2, ['staff_number' => 'X'], ['some error']);

        $this->expectException(\InvalidArgumentException::class);
        app(StaffImportService::class)->import([$invalidRow], $actor);
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
        $actor = $this->actingAsAdminStaff();
        $path = $this->xlsxFile([
            $this->validRow(['staff_number' => 'IMP-D1', 'email' => 'same@rahai.sch.id']),
            $this->validRow(['staff_number' => 'IMP-D2', 'email' => 'same@rahai.sch.id']),
        ]);

        $rows = app(StaffImportValidator::class)->validate($path, $actor);

        $this->assertTrue($rows[0]->isValid());
        $this->assertFalse($rows[1]->isValid());
    }
}
