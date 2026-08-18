<?php

namespace App\Services\Import;

use App\Models\Position;
use App\Models\Staff;
use App\Models\StaffCategory;
use App\Models\User;
use App\Services\AccountAuthorizationMatrix;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Reads and validates an uploaded Staff import file against
 * StaffTemplateBuilder::HEADINGS -- every row, before anything is
 * imported (P2C). Duplicate matching is deterministic: email and NIK
 * only, never name. CREATE-only: a row matching an existing Staff by
 * email or NIK is rejected as a conflict, never silently updated.
 *
 * The role allowlist is ACTOR-aware (P2.1): a row's `role` column is
 * checked against AccountAuthorizationMatrix for the specific User
 * running this import, not a single flat list shared by everyone --
 * super_admin and principal can never be assigned through a bulk import
 * regardless of actor, and admin_staff may only provision `teacher`
 * even though `admin_staff`/`finance_staff`/`management` were previously
 * (incorrectly) reachable through this same permission.
 */
class StaffImportValidator
{
    /** @return StaffImportRow[] */
    public function validate(string $path, User $actor): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('Staff') ?? $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, true, false);

        if (empty($rows)) {
            return [];
        }

        $headings = array_map(fn ($h) => trim((string) $h), $rows[0]);
        $results = [];
        $seenEmails = [];
        $seenNiks = [];
        $seenStaffNumbers = [];

        for ($i = 1; $i < count($rows); $i++) {
            $raw = array_combine($headings, array_pad($rows[$i], count($headings), null));

            if (collect($raw)->filter(fn ($v) => $v !== null && trim((string) $v) !== '')->isEmpty()) {
                continue; // blank row, skip silently -- not a data row to report on
            }

            $rowNumber = $i + 1; // 1-indexed, matching what a human sees in Excel
            $data = $this->normalize($raw);
            $errors = $this->validateRow($data, $rowNumber, $actor, $seenEmails, $seenNiks, $seenStaffNumbers);

            $results[] = new StaffImportRow($rowNumber, $data, $errors);
        }

        return $results;
    }

    private function normalize(array $raw): array
    {
        return [
            'staff_number' => trim((string) ($raw['staff_number'] ?? '')),
            'first_name' => trim((string) ($raw['first_name'] ?? '')),
            'last_name' => trim((string) ($raw['last_name'] ?? '')),
            'position' => trim((string) ($raw['position'] ?? '')),
            'staff_category' => trim((string) ($raw['staff_category'] ?? '')),
            'phone' => trim((string) ($raw['phone'] ?? '')),
            'email' => trim((string) ($raw['email'] ?? '')) ?: null,
            'nik' => trim((string) ($raw['nik'] ?? '')) ?: null,
            'hire_date' => trim((string) ($raw['hire_date'] ?? '')),
            'status' => strtolower(trim((string) ($raw['status'] ?? 'active'))),
            'create_login' => strtolower(trim((string) ($raw['create_login'] ?? 'no'))) === 'yes',
            'role' => strtolower(trim((string) ($raw['role'] ?? ''))),
        ];
    }

    private function validateRow(array $data, int $rowNumber, User $actor, array &$seenEmails, array &$seenNiks, array &$seenStaffNumbers): array
    {
        $errors = [];

        $validator = Validator::make($data, [
            'staff_number' => ['required', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'position' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'nik' => ['nullable', 'digits:16'],
            'hire_date' => ['required', 'date'],
            'status' => ['required', 'in:active,on_leave,terminated'],
        ]);
        if ($validator->fails()) {
            $errors = array_merge($errors, $validator->errors()->all());
        }

        if ($data['staff_category'] !== '' && ! StaffCategory::where('name', $data['staff_category'])->exists()) {
            $errors[] = "Unknown Staff Category \"{$data['staff_category']}\".";
        }

        if ($data['staff_number'] !== '') {
            if (isset($seenStaffNumbers[$data['staff_number']])) {
                $errors[] = "Staff Number \"{$data['staff_number']}\" is duplicated within this file (row {$seenStaffNumbers[$data['staff_number']]}).";
            } elseif (Staff::where('staff_number', $data['staff_number'])->exists()) {
                $errors[] = "Staff Number \"{$data['staff_number']}\" already belongs to an existing Staff record.";
            } else {
                $seenStaffNumbers[$data['staff_number']] = $rowNumber;
            }
        }

        if ($data['email']) {
            if (isset($seenEmails[$data['email']])) {
                $errors[] = "Email \"{$data['email']}\" is duplicated within this file (row {$seenEmails[$data['email']]}).";
            } elseif (Staff::where('email', $data['email'])->exists()) {
                $errors[] = "Email \"{$data['email']}\" already belongs to another Staff record.";
            } elseif (\App\Models\User::where('email', $data['email'])->exists()) {
                $errors[] = "Email \"{$data['email']}\" already belongs to another User.";
            } else {
                $seenEmails[$data['email']] = $rowNumber;
            }
        }

        if ($data['nik']) {
            if (isset($seenNiks[$data['nik']])) {
                $errors[] = "NIK \"{$data['nik']}\" is duplicated within this file (row {$seenNiks[$data['nik']]}).";
            } elseif (Staff::where('nik', $data['nik'])->exists()) {
                $errors[] = "NIK \"{$data['nik']}\" already belongs to another Staff record.";
            } else {
                $seenNiks[$data['nik']] = $rowNumber;
            }
        }

        if ($data['create_login']) {
            if (! $data['email']) {
                $errors[] = 'create_login is yes but no email was given -- a login needs an email.';
            }
            if ($data['role'] === '') {
                $errors[] = 'create_login is yes but role is blank.';
            } elseif (! AccountAuthorizationMatrix::canProvision($actor, $data['role'])) {
                $errors[] = "Role \"{$data['role']}\" cannot be provisioned by your account.";
            }
        }

        return $errors;
    }
}
