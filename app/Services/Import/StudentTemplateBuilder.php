<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds the downloadable Student bulk-import template (P2D). Deliberately
 * has NO class/grade column: resolving a class from a spreadsheet cell
 * needs the same academic-year-scoped, effective-dated enrollment
 * invariants ClassStudentService already enforces (Foundation F3) --
 * bulk-writing around that service risks a student silently ending up
 * with an ambiguous or wrong current enrollment. Import creates the
 * Student record only; enroll each one into a Class afterward through
 * the existing Students/Show or Classes/Show Enroll action, which already
 * goes through ClassStudentService correctly. A separate bulk-enrollment
 * import is future work, not this one, if it's ever needed.
 */
class StudentTemplateBuilder
{
    public const VERSION = '1';

    public const HEADINGS = [
        'student_number', 'first_name', 'last_name', 'gender', 'date_of_birth',
        'nik', 'nisn', 'enrollment_date', 'status',
    ];

    public function build(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;

        $data = $spreadsheet->getActiveSheet();
        $data->setTitle('Students');
        $data->fromArray(self::HEADINGS, null, 'A1');
        $data->fromArray([
            'STU-1001', 'Andi', 'Wijaya', 'male', '2016-03-12',
            '3271010101160001', '0012345678', '2026-07-01', 'active',
        ], null, 'A2');
        foreach (range('A', 'I') as $col) {
            $data->getColumnDimension($col)->setAutoSize(true);
        }

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instructions');
        $instructions->fromArray([
            ['Template Version', self::VERSION],
            [''],
            ['Column', 'Notes'],
            ['student_number', 'Required. Must be unique.'],
            ['first_name', 'Required.'],
            ['last_name', 'Required.'],
            ['gender', 'Required. male or female.'],
            ['date_of_birth', 'Required. Format YYYY-MM-DD. Must be in the past.'],
            ['nik', 'Optional. Exactly 16 digits if given, must be unique.'],
            ['nisn', 'Optional. Exactly 10 digits if given, must be unique.'],
            ['enrollment_date', 'Required. Format YYYY-MM-DD.'],
            ['status', 'Required. One of: active, graduated, withdrawn, transferred.'],
            [''],
            ['This import creates NEW student records only.', 'A row matching an existing NISN or NIK is rejected, not overwritten.'],
            ['Class enrollment is NOT part of this import.', 'Enroll each imported student into a Class afterward from their Student page.'],
        ], null, 'A1');
        $instructions->getColumnDimension('A')->setAutoSize(true);
        $instructions->getColumnDimension('B')->setWidth(60);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    public function download(string $filename = 'student-import-template.xlsx'): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $spreadsheet = $this->build();

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
