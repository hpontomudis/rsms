<?php

namespace App\Ai;

/**
 * Structured output for the Daily Journal reflection assistant -- exactly
 * two nullable fields, both optional. No field here is capable of
 * representing journal_date, an ID, a status, or any other Journal fact:
 * this is the DTO half of the "structurally unrepresentable" firewall the
 * V9A-3 architecture review required (the other half is DailyJournalAssistant
 * never holding a reference to DailyJournalService).
 */
final readonly class DailyJournalSuggestion
{
    public function __construct(
        public ?string $reflection,
        public ?string $followUp,
    ) {}

    public function isEmpty(): bool
    {
        return $this->reflection === null && $this->followUp === null;
    }
}
