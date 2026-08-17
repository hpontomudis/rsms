<?php

namespace App\Services\Import;

use App\Models\Position;
use App\Models\Staff;
use App\Models\StaffCategory;
use App\Services\UserProvisioningService;
use Illuminate\Support\Facades\DB;

/**
 * Imports an already-validated batch of Staff rows (P2C). Callers must
 * have already run every row through StaffImportValidator and confirmed
 * zero errors across the WHOLE file -- this class does not re-validate,
 * and does not accept a partial batch. Whole-file-or-nothing: the file is
 * validated in full before the User ever sees a "Confirm Import" button,
 * so by the time this runs there is nothing left to silently skip.
 *
 * One transaction for the whole batch: a mid-import database error (e.g.
 * a race against a row created by someone else between validation and
 * import) rolls back every row already written in this run, rather than
 * leaving a half-imported file with no record of which half.
 */
class StaffImportService
{
    public function __construct(private readonly UserProvisioningService $provisioning) {}

    /**
     * @param  StaffImportRow[]  $rows
     * @return array{staff: Staff, credential: ?array}[]
     */
    public function import(array $rows): array
    {
        if (collect($rows)->contains(fn (StaffImportRow $r) => ! $r->isValid())) {
            throw new \InvalidArgumentException('StaffImportService::import() requires every row to already be valid.');
        }

        return DB::transaction(function () use ($rows) {
            $results = [];

            foreach ($rows as $row) {
                $data = $row->data;

                $position = Position::firstOrCreate(['title' => $data['position']]);
                $staffCategory = $data['staff_category'] !== ''
                    ? StaffCategory::where('name', $data['staff_category'])->first()
                    : null;

                $staff = Staff::create([
                    'staff_number' => $data['staff_number'],
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'position_id' => $position->id,
                    'staff_category_id' => $staffCategory?->id,
                    'phone' => $data['phone'],
                    'email' => $data['email'],
                    'nik' => $data['nik'],
                    'hire_date' => $data['hire_date'],
                    'status' => $data['status'],
                ]);

                $credential = null;
                if ($data['create_login']) {
                    $provisioned = $this->provisioning->provision(
                        trim($data['first_name'].' '.$data['last_name']),
                        $data['email'],
                        $data['role'],
                    );
                    $staff->update(['user_id' => $provisioned['user']->id]);
                    $credential = [
                        'name' => $staff->fullName(),
                        'email' => $data['email'],
                        'password' => $provisioned['temporaryPassword'],
                        'role' => $data['role'],
                        'status' => 'created',
                    ];
                }

                $results[] = ['staff' => $staff, 'credential' => $credential];
            }

            return $results;
        });
    }
}
