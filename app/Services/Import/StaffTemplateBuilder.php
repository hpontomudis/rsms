<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds the downloadable Staff bulk-import template (P2C). Synthetic
 * example data only -- never real Rahai staff. A "Template Version" cell
 * on the Instructions sheet is the lightweight version marker so a future
 * schema change can detect and reject a stale upload rather than silently
 * misreading it.
 */
class StaffTemplateBuilder
{
    public const VERSION = '1';

    public const HEADINGS = [
        'staff_number', 'first_name', 'last_name', 'position', 'staff_category',
        'phone', 'email', 'nik', 'hire_date', 'status', 'create_login', 'role',
    ];

    public function build(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;

        $data = $spreadsheet->getActiveSheet();
        $data->setTitle('Staff');
        $data->fromArray(self::HEADINGS, null, 'A1');
        $data->fromArray([
            'STF-1001', 'Siti', 'Rahayu', 'Homeroom Teacher', 'Teacher',
            '0812-3456-7890', 'siti.rahayu@example.com', '3271010101900001', '2024-07-01', 'active', 'yes', 'teacher',
        ], null, 'A2');
        foreach (range('A', 'L') as $col) {
            $data->getColumnDimension($col)->setAutoSize(true);
        }

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instructions');
        $instructions->fromArray([
            ['Template Version', self::VERSION],
            [''],
            ['Column', 'Notes'],
            ['staff_number', 'Required. Must be unique.'],
            ['first_name', 'Required.'],
            ['last_name', 'Required.'],
            ['position', 'Required. An existing position title, or a new one to create it.'],
            ['staff_category', 'Optional. Must match an existing Staff Category name exactly.'],
            ['phone', 'Required.'],
            ['email', 'Optional, but must be unique if given.'],
            ['nik', 'Optional. Exactly 16 digits if given, must be unique.'],
            ['hire_date', 'Required. Format YYYY-MM-DD.'],
            ['status', 'Required. One of: active, on_leave, terminated.'],
            ['create_login', 'yes or no. If yes, a login account is created with a temporary password.'],
            ['role', 'Required if create_login is yes. One of: teacher, admin_staff, finance_staff, management.'],
            [''],
            ['This import creates NEW staff only.', 'A row matching an existing email or NIK is rejected, not overwritten.'],
        ], null, 'A1');
        $instructions->getColumnDimension('A')->setAutoSize(true);
        $instructions->getColumnDimension('B')->setWidth(60);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    public function download(string $filename = 'staff-import-template.xlsx'): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $spreadsheet = $this->build();

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
