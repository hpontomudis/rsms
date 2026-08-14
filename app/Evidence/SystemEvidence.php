<?php

namespace App\Evidence;

use Illuminate\Support\Carbon;

/**
 * What a provider hands back. Read-only display facts -- a count, a boolean, a
 * label -- for a human to weigh. Nothing here is, or ever becomes, a rating.
 */
final class SystemEvidence
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly EvidenceAvailability $availability,
        public readonly ?float $numericValue = null,
        public readonly ?bool $booleanValue = null,
        public readonly ?string $textValue = null,
        public readonly ?Carbon $rangeStart = null,
        public readonly ?Carbon $rangeEnd = null,
        public readonly ?string $note = null,
    ) {}

    public static function unavailable(string $key, string $label, string $note): self
    {
        return new self($key, $label, EvidenceAvailability::Unavailable, note: $note);
    }

    public function isAvailable(): bool
    {
        return $this->availability === EvidenceAvailability::Available;
    }
}
