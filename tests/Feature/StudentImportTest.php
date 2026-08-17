<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use App\Services\Import\StudentImportService;
use App\Services\Import\StudentImportValidator;
use App\Services\Import\StudentTemplateBuilder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    private function xlsxFile(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Students');
        $sheet->fromArray(StudentTemplateBuilder::HEADINGS, null, 'A1');
        $r = 2;
        foreach ($rows as $row) {
            $sheet->fromArray($row, null, "A{$r}");
            $r++;
        }

        $path = tempnam(sys_get_temp_dir(), 'student_import_test').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function validRow(array $overrides = []): array
    {
        $defaults = [
            'student_number' => 'IMPSTU-'.uniqid(),
            'first_name' => 'Imported', 'last_name' => 'Student',
            'gender' => 'male', 'date_of_birth' => '2016-01-01',
            'nik' => '', 'nisn' => '', 'enrollment_date' => '2026-07-01', 'status' => 'active',
        ];
        $merged = array_merge($defaults, $overrides);

        return array_values(array_replace(array_flip(StudentTemplateBuilder::HEADINGS), $merged));
    }

    private function actingAsAdminStaff(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin_staff');
        $this->actingAs($user);

        return $user;
    }

    public function test_template_download_is_accessible(): void
    {
        $this->actingAsAdminStaff();

        Livewire::test(\App\Livewire\Students\Import::class)
            ->call('downloadTemplate')
            ->assertStatus(200);
    }

    public function test_a_valid_row_imports_successfully(): void
    {
        $this->actingAsAdminStaff();
        $path = $this->xlsxFile([$this->validRow(['student_number' => 'IMPSTU-VALID-1'])]);

        $rows = app(StudentImportValidator::class)->validate($path);
        $this->assertTrue($rows[0]->isValid(), implode(', ', $rows[0]->errors));

        $created = app(StudentImportService::class)->import($rows);
        $this->assertCount(1, $created);
        $this->assertDatabaseHas('students', ['student_number' => 'IMPSTU-VALID-1']);
    }

    public function test_an_invalid_nisn_length_is_reported(): void
    {
        $this->actingAsAdminStaff();
        $path = $this->xlsxFile([$this->validRow(['student_number' => 'IMPSTU-BADNISN', 'nisn' => '123'])]);

        $rows = app(StudentImportValidator::class)->validate($path);

        $this->assertFalse($rows[0]->isValid());
    }

    public function test_a_nik_containing_letters_is_rejected(): void
    {
        $this->actingAsAdminStaff();
        $path = $this->xlsxFile([$this->validRow(['student_number' => 'IMPSTU-BADNIK', 'nik' => 'ABCDEFGHIJKLMNOP'])]);

        $rows = app(StudentImportValidator::class)->validate($path);

        $this->assertFalse($rows[0]->isValid());
    }

    public function test_a_duplicate_nisn_against_an_existing_student_is_rejected_not_overwritten(): void
    {
        $this->actingAsAdminStaff();
        Student::create([
            'student_number' => 'EXISTING-STU-1', 'first_name' => 'A', 'last_name' => 'B',
            'gender' => 'female', 'date_of_birth' => '2015-01-01', 'enrollment_date' => '2025-07-01',
            'nisn' => '9988776655',
        ]);
        $path = $this->xlsxFile([$this->validRow(['student_number' => 'IMPSTU-DUPE', 'nisn' => '9988776655'])]);

        $rows = app(StudentImportValidator::class)->validate($path);

        $this->assertFalse($rows[0]->isValid());
        $this->assertStringContainsString('already belongs', implode(' ', $rows[0]->errors));
        $this->assertDatabaseHas('students', ['student_number' => 'EXISTING-STU-1', 'first_name' => 'A']);
    }

    public function test_no_class_or_grade_column_exists_in_the_template(): void
    {
        $this->assertNotContains('class', StudentTemplateBuilder::HEADINGS);
        $this->assertNotContains('grade', StudentTemplateBuilder::HEADINGS);
    }

    public function test_import_service_refuses_a_batch_containing_any_invalid_row(): void
    {
        $invalidRow = new \App\Services\Import\StudentImportRow(2, ['student_number' => 'X'], ['some error']);

        $this->expectException(\InvalidArgumentException::class);
        app(StudentImportService::class)->import([$invalidRow]);
    }

    public function test_import_is_denied_to_a_teacher(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $this->assertFalse($teacher->can('import', Student::class));
    }

    public function test_two_rows_with_the_same_nik_in_one_file_are_both_flagged(): void
    {
        $this->actingAsAdminStaff();
        $path = $this->xlsxFile([
            $this->validRow(['student_number' => 'IMPSTU-N1', 'nik' => '1234567890123456']),
            $this->validRow(['student_number' => 'IMPSTU-N2', 'nik' => '1234567890123456']),
        ]);

        $rows = app(StudentImportValidator::class)->validate($path);

        $this->assertTrue($rows[0]->isValid());
        $this->assertFalse($rows[1]->isValid());
    }

    public function test_blank_nik_and_nisn_do_not_collide_across_multiple_imported_rows(): void
    {
        $this->actingAsAdminStaff();
        $path = $this->xlsxFile([
            $this->validRow(['student_number' => 'IMPSTU-B1']),
            $this->validRow(['student_number' => 'IMPSTU-B2']),
        ]);

        $rows = app(StudentImportValidator::class)->validate($path);
        $this->assertTrue($rows[0]->isValid());
        $this->assertTrue($rows[1]->isValid());

        $created = app(StudentImportService::class)->import($rows);
        $this->assertCount(2, $created);
    }
}
