<?php

namespace Tests\Feature;

use App\Ai\AiGenerationRequest;
use App\Ai\DeepSeekAiProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeepSeekAiProviderTest extends TestCase
{
    private function request(): AiGenerationRequest
    {
        return new AiGenerationRequest(
            systemInstructions: 'You are a helpful assistant.',
            userContent: 'Say hello.',
            temperature: 0.5,
            maxOutputTokens: 100,
        );
    }

    public function test_missing_api_key_fails_without_a_network_call(): void
    {
        Http::fake();

        $provider = new DeepSeekAiProvider(apiKey: null, model: 'deepseek-chat', timeoutSeconds: 25);
        $result = $provider->generate($this->request());

        $this->assertFalse($result->isSuccess());
        $this->assertSame('provider_not_configured', $result->errorCode);
        Http::assertNothingSent();
    }

    public function test_successful_response_is_parsed_into_a_provider_neutral_result(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => 'Hello there!']],
                ],
                'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 4, 'total_tokens' => 16],
                'model' => 'deepseek-chat',
            ], 200),
        ]);

        $provider = new DeepSeekAiProvider(apiKey: 'test-key', model: 'deepseek-chat', timeoutSeconds: 25);
        $result = $provider->generate($this->request());

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Hello there!', $result->text);
        $this->assertSame(12, $result->inputTokens);
        $this->assertSame(4, $result->outputTokens);
        $this->assertSame('deepseek', $result->provider);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.deepseek.com/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $request['messages'][0]['role'] === 'system'
                && $request['messages'][1]['role'] === 'user';
        });
    }

    public function test_rate_limited_response_is_reported_distinctly(): void
    {
        Http::fake(['api.deepseek.com/*' => Http::response([], 429)]);

        $provider = new DeepSeekAiProvider(apiKey: 'test-key', model: 'deepseek-chat', timeoutSeconds: 25);
        $result = $provider->generate($this->request());

        $this->assertFalse($result->isSuccess());
        $this->assertSame('provider_rate_limited', $result->errorCode);
    }

    public function test_generic_error_response_is_reported_without_a_stack_trace(): void
    {
        Http::fake(['api.deepseek.com/*' => Http::response(['error' => 'boom'], 500)]);

        $provider = new DeepSeekAiProvider(apiKey: 'test-key', model: 'deepseek-chat', timeoutSeconds: 25);
        $result = $provider->generate($this->request());

        $this->assertFalse($result->isSuccess());
        $this->assertSame('provider_error', $result->errorCode);
    }

    public function test_empty_content_is_reported_as_empty_response(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => '']]],
                'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 0],
            ], 200),
        ]);

        $provider = new DeepSeekAiProvider(apiKey: 'test-key', model: 'deepseek-chat', timeoutSeconds: 25);
        $result = $provider->generate($this->request());

        $this->assertFalse($result->isSuccess());
        $this->assertSame('empty_response', $result->errorCode);
    }

    public function test_connection_timeout_is_reported_distinctly(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
        });

        $provider = new DeepSeekAiProvider(apiKey: 'test-key', model: 'deepseek-chat', timeoutSeconds: 25);
        $result = $provider->generate($this->request());

        $this->assertFalse($result->isSuccess());
        $this->assertSame('timeout', $result->errorCode);
    }
}
