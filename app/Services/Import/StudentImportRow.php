<?php

namespace App\Services\Import;

/**
 * One validated (or rejected) row from a Student import file. See
 * StaffImportRow's docblock -- same shape, kept as a separate class
 * rather than shared, since Staff and Student rows validate against
 * different schemas and it's clearer for each importer to own its type.
 */
final readonly class StudentImportRow
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
