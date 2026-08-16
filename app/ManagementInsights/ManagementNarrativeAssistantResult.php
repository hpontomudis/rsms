<?php

namespace App\ManagementInsights;

/**
 * Assistant-level result -- same pattern as DailyJournalAssistantResult
 * and TeachingModuleAssistantResult. Provider-transport success (recorded
 * on `ai_generations.status` via `AiGenerationResult`) is a different
 * question from whether the response was interpretable into a usable
 * suggestion (recorded here). The two can legitimately disagree.
 *
 * `generationId` may be null when the failure was pre-provider (e.g.
 * rate-limited or authorization refusal caught before AiGenerationService
 * was invoked).
 */
final readonly class ManagementNarrativeAssistantResult
{
    private function __construct(
        public string $status,
        public ?ManagementNarrativeSuggestion $suggestion,
        public ?int $generationId,
    ) {}

    public static function success(ManagementNarrativeSuggestion $suggestion, int $generationId): self
    {
        return new self('success', $suggestion, $generationId);
    }

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
