<?php

namespace App\Services\Import;

/**
 * One validated (or rejected) row from a Staff import file. `errors` empty
 * means the row is ready to import; otherwise it is never written, and the
 * whole file's errors are shown together before anything imports (P2C:
 * validate the full file first, import only after preview/confirmation).
 */
final readonly class StaffImportRow
{
    public function __construct(
        public int $rowNumber,
        public array $data,
        public array $errors = [],
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
