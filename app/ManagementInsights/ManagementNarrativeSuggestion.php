<?php

namespace App\ManagementInsights;

/**
 * Structured output for the Management Narrative assistant -- one optional
 * summary paragraph plus a list of plain-string "areas to review." No
 * priority score, no per-point severity, no ranking, no field capable of
 * naming a person or referring to a specific record. Prioritisation stays
 * a deterministic, provider-owned decision (severity on `ManagementInsight`);
 * the AI never re-ranks.
 */
final readonly class ManagementNarrativeSuggestion
{
    public function __construct(
        public ?string $summary,
        /** @var array<int, string> */
        public array $attentionPoints,
    ) {}

    public function isEmpty(): bool
    {
        return $this->summary === null && $this->attentionPoints === [];
    }
}
