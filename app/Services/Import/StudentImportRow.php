<?php

namespace App\Services\Import;

use Livewire\Wireable;

/**
 * One validated (or rejected) row from a Student import file. See
 * StaffImportRow's docblock -- same shape, kept as a separate class
 * rather than shared, since Staff and Student rows validate against
 * different schemas and it's clearer for each importer to own its type.
 * Wireable for the same reason: Students\Import holds these in a public
 * `$preview` property that Livewire must serialize.
 */
final readonly class StudentImportRow implements Wireable
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

    public function toLivewire(): array
    {
        return [
            'rowNumber' => $this->rowNumber,
            'data' => $this->data,
            'errors' => $this->errors,
        ];
    }

    public static function fromLivewire($value): self
    {
        return new self(
            rowNumber: $value['rowNumber'],
            data: $value['data'],
            errors: $value['errors'],
        );
    }
}
