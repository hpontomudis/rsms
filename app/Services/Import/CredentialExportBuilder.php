<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * One-time credential handoff export (P2C). Built entirely in memory from
 * data already held in the current request/response -- never reads
 * anything back out of the database, because the plaintext password was
 * never stored there in the first place. There is deliberately no
 * corresponding "view past credentials" screen or stored file.
 *
 * @see UserProvisioningService
 */
class CredentialExportBuilder
{
    /**
     * @param  array{name: string, email: string, password: string, role: string, status: string}[]  $credentials
     */
    public function download(array $credentials, string $filename = 'staff-credentials.xlsx'): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Credentials');
        $sheet->fromArray(['Name', 'Email', 'Temporary Password', 'Role', 'Status'], null, 'A1');

        $row = 2;
        foreach ($credentials as $credential) {
            $sheet->fromArray([
                $credential['name'], $credential['email'], $credential['password'], $credential['role'], $credential['status'],
            ], null, "A{$row}");
            $row++;
        }

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
