<?php

namespace App\Ai;

use Illuminate\Support\Facades\Http;

/**
 * The one real provider adapter for V9A (architecture review item 4: exactly
 * one, no multi-provider routing, no automatic fallback). Talks to
 * Anthropic's Messages API over plain HTTP via Laravel's own Http facade --
 * no vendor SDK dependency, nothing added to composer.json. Swapping vendors
 * later means writing one new class implementing AiProvider; nothing that
 * calls AiGenerationService needs to change.
 *
 * Chosen for: strong Indonesian and English output, native structured-output
 * support via tool use (not needed by CommunicationAssistant today, but
 * available without a new adapter when a future structured assistant is
 * approved), per-request token-usage reporting on every response, a plain
 * REST+API-key contract with no SDK to depend on, and a straightforward
 * synchronous request/response shape that fits V9A's synchronous-only
 * design (no queue, no worker).
 */
class AnthropicAiProvider implements AiProvider
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    private const API_VERSION = '2023-06-01';

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly int $timeoutSeconds,
    ) {}

    public function generate(AiGenerationRequest $request): AiGenerationResult
    {
        if (! $this->apiKey) {
            return AiGenerationResult::failed('provider_not_configured', 'anthropic', $this->model);
        }

        $start = microtime(true);

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
                'content-type' => 'application/json',
            ])
                ->timeout($this->timeoutSeconds)
                ->post(self::API_URL, [
                    'model' => $this->model,
                    'max_tokens' => $request->maxOutputTokens,
                    'temperature' => $request->temperature,
                    'system' => $request->systemInstructions,
                    'messages' => [
                        ['role' => 'user', 'content' => $request->userContent],
                    ],
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException) {
            return AiGenerationResult::failed('timeout', 'anthropic', $this->model, $this->elapsedMs($start));
        }

        $durationMs = $this->elapsedMs($start);

        if ($response->status() === 429) {
            return AiGenerationResult::failed('provider_rate_limited', 'anthropic', $this->model, $durationMs);
        }

        if ($response->failed()) {
            return AiGenerationResult::failed('provider_error', 'anthropic', $this->model, $durationMs);
        }

        $data = $response->json();
        $text = collect($data['content'] ?? [])->firstWhere('type', 'text')['text'] ?? null;

        if ($text === null || trim($text) === '') {
            return AiGenerationResult::failed('empty_response', 'anthropic', $this->model, $durationMs);
        }

        return AiGenerationResult::success(
            text: $text,
            inputTokens: $data['usage']['input_tokens'] ?? null,
            outputTokens: $data['usage']['output_tokens'] ?? null,
            provider: 'anthropic',
            model: $this->model,
            durationMs: $durationMs,
        );
    }

    private function elapsedMs(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}
