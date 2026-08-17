<?php

namespace App\Services\Import;

use App\Models\Student;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Reads and validates an uploaded Student import file (P2D). Same
 * discipline as StaffImportValidator: deterministic duplicate matching
 * (NISN and NIK, never name), CREATE-only, whole file validated before
 * any row imports. No class/grade column -- see StudentTemplateBuilder.
 */
class StudentImportValidator
{
    /** @return StudentImportRow[] */
    public function validate(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('Students') ?? $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, true, false);

        if (empty($rows)) {
            return [];
        }

        $headings = array_map(fn ($h) => trim((string) $h), $rows[0]);
        $results = [];
        $seenNisns = [];
        $seenNiks = [];
        $seenStudentNumbers = [];

        for ($i = 1; $i < count($rows); $i++) {
            $raw = array_combine($headings, array_pad($rows[$i], count($headings), null));

            if (collect($raw)->filter(fn ($v) => $v !== null && trim((string) $v) !== '')->isEmpty()) {
                continue;
            }

            $rowNumber = $i + 1;
            $data = $this->normalize($raw);
            $errors = $this->validateRow($data, $rowNumber, $seenNisns, $seenNiks, $seenStudentNumbers);

            $results[] = new StudentImportRow($rowNumber, $data, $errors);
        }

        return $results;
    }

    private function normalize(array $raw): array
    {
        return [
            'student_number' => trim((string) ($raw['student_number'] ?? '')),
            'first_name' => trim((string) ($raw['first_name'] ?? '')),
            'last_name' => trim((string) ($raw['last_name'] ?? '')),
            'gender' => strtolower(trim((string) ($raw['gender'] ?? ''))),
            'date_of_birth' => trim((string) ($raw['date_of_birth'] ?? '')),
            'nik' => trim((string) ($raw['nik'] ?? '')) ?: null,
            'nisn' => trim((string) ($raw['nisn'] ?? '')) ?: null,
            'enrollment_date' => trim((string) ($raw['enrollment_date'] ?? '')),
            'status' => strtolower(trim((string) ($raw['status'] ?? 'active'))),
        ];
    }

    private function validateRow(array $data, int $rowNumber, array &$seenNisns, array &$seenNiks, array &$seenStudentNumbers): array
    {
        $errors = [];

        $validator = Validator::make($data, [
            'student_number' => ['required', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', 'in:male,female'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'nik' => ['nullable', 'digits:16'],
            'nisn' => ['nullable', 'digits:10'],
            'enrollment_date' => ['required', 'date'],
            'status' => ['required', 'in:active,graduated,withdrawn,transferred'],
        ]);
        if ($validator->fails()) {
            $errors = array_merge($errors, $validator->errors()->all());
        }

        if ($data['student_number'] !== '') {
            if (isset($seenStudentNumbers[$data['student_number']])) {
                $errors[] = "Student Number \"{$data['student_number']}\" is duplicated within this file (row {$seenStudentNumbers[$data['student_number']]}).";
            } elseif (Student::where('student_number', $data['student_number'])->exists()) {
                $errors[] = "Student Number \"{$data['student_number']}\" already belongs to an existing Student record.";
            } else {
                $seenStudentNumbers[$data['student_number']] = $rowNumber;
            }
        }

        if ($data['nisn']) {
            if (isset($seenNisns[$data['nisn']])) {
                $errors[] = "NISN \"{$data['nisn']}\" is duplicated within this file (row {$seenNisns[$data['nisn']]}).";
            } elseif (Student::where('nisn', $data['nisn'])->exists()) {
                $errors[] = "NISN \"{$data['nisn']}\" already belongs to another Student record.";
            } else {
                $seenNisns[$data['nisn']] = $rowNumber;
            }
        }

        if ($data['nik']) {
            if (isset($seenNiks[$data['nik']])) {
                $errors[] = "NIK \"{$data['nik']}\" is duplicated within this file (row {$seenNiks[$data['nik']]}).";
            } elseif (Student::where('nik', $data['nik'])->exists()) {
                $errors[] = "NIK \"{$data['nik']}\" already belongs to another Student record.";
            } else {
                $seenNiks[$data['nik']] = $rowNumber;
            }
        }

        return $errors;
    }
}
