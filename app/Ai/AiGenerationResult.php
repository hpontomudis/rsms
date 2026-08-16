<?php

namespace App\Ai;

/**
 * Provider-neutral output. `text` is transient -- returned to the caller for
 * display, never written by AiGenerationService into `ai_generations`
 * (which stores metadata only, per the V9A architecture review).
 *
 * `generationId` is populated by AiGenerationService after it persists the
 * metadata row, not by the provider -- it is how a caller later marks
 * `accepted_at` when the User clicks Apply, without the DTO itself needing
 * to know about persistence.
 */
final readonly class AiGenerationResult
{
    private function __construct(
        public string $status,
        public ?string $text,
        public ?int $inputTokens,
        public ?int $outputTokens,
        public ?int $totalTokens,
        public ?string $provider,
        public ?string $model,
        public ?int $durationMs,
        public ?string $errorCode,
        public ?int $generationId = null,
    ) {}

    public static function success(
        string $text,
        ?int $inputTokens,
        ?int $outputTokens,
        ?string $provider,
        ?string $model,
        ?int $durationMs,
    ): self {
        $totalTokens = ($inputTokens !== null && $outputTokens !== null) ? $inputTokens + $outputTokens : null;

        return new self('success', $text, $inputTokens, $outputTokens, $totalTokens, $provider, $model, $durationMs, null);
    }

    public static function failed(string $errorCode, ?string $provider = null, ?string $model = null, ?int $durationMs = null): self
    {
        return new self('failed', null, null, null, null, $provider, $model, $durationMs, $errorCode);
    }

    public static function rateLimited(): self
    {
        return new self('rate_limited', null, null, null, null, null, null, null, 'rate_limited');
    }

    public static function disabled(): self
    {
        return new self('failed', null, null, null, null, null, null, null, 'ai_disabled');
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    /** A copy carrying the persisted ai_generations row id, for the caller to reference on Apply. */
    public function withGenerationId(int $generationId): self
    {
        return new self(
            $this->status, $this->text, $this->inputTokens, $this->outputTokens, $this->totalTokens,
            $this->provider, $this->model, $this->durationMs, $this->errorCode, $generationId,
        );
    }
}
