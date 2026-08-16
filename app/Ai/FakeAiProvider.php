<?php

namespace App\Ai;

/**
 * The ONLY provider automated tests may use -- no real network call ever
 * runs in the suite (V9A architecture review). Configure a behaviour before
 * calling the assistant under test; `lastRequest()` lets a test inspect
 * exactly what an assistant assembled, which is how data-minimization and
 * prompt-injection tests work (they never need a real provider to prove the
 * request was built safely).
 */
class FakeAiProvider implements AiProvider
{
    private string $behavior = 'success';

    private string $responseText = 'A generated suggestion.';

    private ?int $inputTokens = 42;

    private ?int $outputTokens = 84;

    private ?AiGenerationRequest $lastRequest = null;

    private int $callCount = 0;

    public function willSucceedWith(string $text, ?int $inputTokens = 42, ?int $outputTokens = 84): self
    {
        $this->behavior = 'success';
        $this->responseText = $text;
        $this->inputTokens = $inputTokens;
        $this->outputTokens = $outputTokens;

        return $this;
    }

    public function willTimeOut(): self
    {
        $this->behavior = 'timeout';

        return $this;
    }

    public function willFail(): self
    {
        $this->behavior = 'provider_error';

        return $this;
    }

    public function willReturnEmpty(): self
    {
        $this->behavior = 'empty';

        return $this;
    }

    public function generate(AiGenerationRequest $request): AiGenerationResult
    {
        $this->lastRequest = $request;
        $this->callCount++;

        return match ($this->behavior) {
            'timeout' => AiGenerationResult::failed('timeout', 'fake', 'fake-model', 25000),
            'provider_error' => AiGenerationResult::failed('provider_error', 'fake', 'fake-model', 120),
            'empty' => AiGenerationResult::failed('empty_response', 'fake', 'fake-model', 120),
            default => AiGenerationResult::success(
                text: $this->responseText,
                inputTokens: $this->inputTokens,
                outputTokens: $this->outputTokens,
                provider: 'fake',
                model: 'fake-model',
                durationMs: 120,
            ),
        };
    }

    public function lastRequest(): ?AiGenerationRequest
    {
        return $this->lastRequest;
    }

    public function callCount(): int
    {
        return $this->callCount;
    }
}
