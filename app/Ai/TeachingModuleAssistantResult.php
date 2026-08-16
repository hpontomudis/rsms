<?php

namespace App\Ai;

/**
 * Assistant-level result -- a thin layer ABOVE the unmodified AiGenerationResult.
 * Same shape as DailyJournalAssistantResult (V9A-3), extended to a five-field
 * suggestion: ai_generations.status (via AiGenerationResult) answers "did the
 * provider respond successfully" -- pure transport. This DTO answers a
 * DIFFERENT question: "did that response contain a usable structured
 * suggestion." The two can legitimately disagree: a provider call can
 * succeed (tokens spent, ai_generations.status = 'success') while returning
 * text that is not valid JSON, or valid JSON with none of the five fields
 * usable -- status here is then 'unusable'. No new ai_generations column
 * exists to record this distinction; it lives only in this transient result.
 */
final readonly class TeachingModuleAssistantResult
{
    private function __construct(
        public string $status,
        public ?TeachingModuleSuggestion $suggestion,
        public ?int $generationId,
    ) {}

    public static function success(TeachingModuleSuggestion $suggestion, int $generationId): self
    {
        return new self('success', $suggestion, $generationId);
    }

    /** Provider transport succeeded, but the response could not be interpreted into a usable suggestion. */
    public static function unusable(?int $generationId): self
    {
        return new self('unusable', null, $generationId);
    }

    public static function failed(): self
    {
        return new self('failed', null, null);
    }

    public static function rateLimited(): self
    {
        return new self('rate_limited', null, null);
    }

    public function isUsable(): bool
    {
        return $this->status === 'success';
    }
}
