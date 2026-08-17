<?php

namespace App\Services\Import;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Imports an already-validated batch of Student rows (P2D). Same
 * whole-file-or-nothing discipline as StaffImportService -- see its
 * docblock. Creates Student records only; no class enrollment (see
 * StudentTemplateBuilder for why that's deliberately out of scope here).
 */
class StudentImportService
{
    /**
     * @param  StudentImportRow[]  $rows
     * @return Student[]
     */
    public function import(array $rows): array
    {
        if (collect($rows)->contains(fn (StudentImportRow $r) => ! $r->isValid())) {
            throw new \InvalidArgumentException('StudentImportService::import() requires every row to already be valid.');
        }

        return DB::transaction(function () use ($rows) {
            $created = [];

            foreach ($rows as $row) {
                $data = $row->data;

                $created[] = Student::create([
                    'student_number' => $data['student_number'],
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'gender' => $data['gender'],
                    'date_of_birth' => $data['date_of_birth'],
                    'nik' => $data['nik'],
                    'nisn' => $data['nisn'],
                    'enrollment_date' => $data['enrollment_date'],
                    'status' => $data['status'],
                ]);
            }

            return $created;
        });
    }
}
