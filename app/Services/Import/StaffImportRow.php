<?php

namespace App\Services\Import;

use Livewire\Wireable;

/**
 * One validated (or rejected) row from a Staff import file. `errors` empty
 * means the row is ready to import; otherwise it is never written, and the
 * whole file's errors are shown together before anything imports (P2C:
 * validate the full file first, import only after preview/confirmation).
 *
 * Implements Wireable because Staff\Import holds these in a PUBLIC
 * `$preview` property between the validate and confirm steps. Livewire has
 * to serialize every public property into its component snapshot, and it
 * refuses ("Property type not supported in Livewire for property: ...") on
 * a plain PHP object it has no synthesizer for -- which broke the import
 * screen server-side even once the request reached this far. Only the
 * three constructor values cross the wire; nothing is derived or hidden.
 */
final readonly class StaffImportRow implements Wireable
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
