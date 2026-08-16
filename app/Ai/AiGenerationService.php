<?php

namespace App\Ai;

use App\Models\AiGeneration;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The one place a generation actually happens: enabled-check, then
 * rate-limit checks (BEFORE the provider is ever called -- a refused
 * request must cost nothing), then the provider call, then a metadata-only
 * `ai_generations` row. Assistant classes never call AiProvider directly and
 * never touch `ai_generations` themselves.
 *
 * Rate limiting deliberately checks the per-minute limit via Laravel's own
 * cache-backed RateLimiter (no new schema) and the daily limit via a COUNT
 * against ai_generations itself (data that already has to exist for audit).
 * A rate-limited attempt is logged but does NOT count toward the daily cap
 * -- otherwise a User who trips the per-minute limit would erode their own
 * daily allowance for requests the provider never even saw.
 */
class AiGenerationService
{
    public function __construct(private readonly AiProvider $provider) {}

    public function generate(User $actor, string $useCase, string $promptVersion, AiGenerationRequest $request): AiGenerationResult
    {
        if (! config('ai.enabled')) {
            return $this->log($actor, $useCase, $promptVersion, AiGenerationResult::disabled());
        }

        if ($this->tooManyPerMinute($actor) || $this->tooManyToday($actor)) {
            return $this->log($actor, $useCase, $promptVersion, AiGenerationResult::rateLimited());
        }

        RateLimiter::hit($this->minuteKey($actor), 60);

        $result = $this->provider->generate($request);

        return $this->log($actor, $useCase, $promptVersion, $result);
    }

    /** Marks accepted_at = now(). The canonical save itself happens elsewhere, through the normal domain service. */
    public function markAccepted(int $generationId): void
    {
        AiGeneration::whereKey($generationId)->update(['accepted_at' => now()]);
    }

    private function tooManyPerMinute(User $actor): bool
    {
        return RateLimiter::tooManyAttempts($this->minuteKey($actor), config('ai.rate_limit.per_minute'));
    }

    private function tooManyToday(User $actor): bool
    {
        return AiGeneration::where('user_id', $actor->id)
            ->whereIn('status', ['success', 'failed'])
            ->whereDate('created_at', now()->toDateString())
            ->count() >= config('ai.rate_limit.per_day');
    }

    private function minuteKey(User $actor): string
    {
        return "ai-generate:{$actor->id}";
    }

    private function log(User $actor, string $useCase, string $promptVersion, AiGenerationResult $result): AiGenerationResult
    {
        $generation = AiGeneration::create([
            'user_id' => $actor->id,
            'use_case' => $useCase,
            'provider' => $result->provider,
            'model' => $result->model,
            'prompt_version' => $promptVersion,
            'status' => $result->status,
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
            'total_tokens' => $result->totalTokens,
            'duration_ms' => $result->durationMs,
            'error_code' => $result->errorCode,
        ]);

        return $result->withGenerationId($generation->id);
    }
}
