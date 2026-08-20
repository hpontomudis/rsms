<?php

namespace App\Ai;

use Illuminate\Support\Facades\Http;

/**
 * Second provider adapter, added for local testing convenience alongside
 * AnthropicAiProvider -- selected via `AI_PROVIDER=deepseek` (config/ai.php),
 * routed in AppServiceProvider::register(). Still exactly one active
 * provider at a time, no automatic fallback between them -- only which one
 * is active changed, not the "one provider" design itself.
 *
 * DeepSeek's API is OpenAI-Chat-Completions-compatible: POST /chat/completions
 * with a `messages` array (system + user roles), Bearer auth, a
 * `choices[0].message.content` response, and `usage.prompt_tokens` /
 * `usage.completion_tokens` for token accounting.
 */
class DeepSeekAiProvider implements AiProvider
{
    private const API_URL = 'https://api.deepseek.com/chat/completions';

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly int $timeoutSeconds,
    ) {}

    public function generate(AiGenerationRequest $request): AiGenerationResult
    {
        if (! $this->apiKey) {
            return AiGenerationResult::failed('provider_not_configured', 'deepseek', $this->model);
        }

        $start = microtime(true);

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout($this->timeoutSeconds)
                ->post(self::API_URL, [
                    'model' => $this->model,
                    'max_tokens' => $request->maxOutputTokens,
                    'temperature' => $request->temperature,
                    'messages' => [
                        ['role' => 'system', 'content' => $request->systemInstructions],
                        ['role' => 'user', 'content' => $request->userContent],
                    ],
                    'stream' => false,
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException) {
            return AiGenerationResult::failed('timeout', 'deepseek', $this->model, $this->elapsedMs($start));
        }

        $durationMs = $this->elapsedMs($start);

        if ($response->status() === 429) {
            return AiGenerationResult::failed('provider_rate_limited', 'deepseek', $this->model, $durationMs);
        }

        if ($response->failed()) {
            return AiGenerationResult::failed('provider_error', 'deepseek', $this->model, $durationMs);
        }

        $data = $response->json();
        $text = $data['choices'][0]['message']['content'] ?? null;

        if ($text === null || trim($text) === '') {
            return AiGenerationResult::failed('empty_response', 'deepseek', $this->model, $durationMs);
        }

        return AiGenerationResult::success(
            text: $text,
            inputTokens: $data['usage']['prompt_tokens'] ?? null,
            outputTokens: $data['usage']['completion_tokens'] ?? null,
            provider: 'deepseek',
            model: $this->model,
            durationMs: $durationMs,
        );
    }

    private function elapsedMs(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}
