<?php

namespace Tests\Feature;

use App\Ai\AiGenerationRequest;
use App\Ai\AiGenerationService;
use App\Models\AiGeneration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Provider-neutral infrastructure only -- no Communication, no assistant.
 * FakeAiProvider is bound for every test in Tests\TestCase, so nothing here
 * (or anywhere else in the suite) ever makes a real network call.
 */
class AiInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    // ------------------------------------------------------------ provider

    public function test_a_successful_generation_returns_text_and_token_metadata(): void
    {
        $this->fakeAiProvider()->willSucceedWith('Suggested text.', inputTokens: 10, outputTokens: 20);

        $result = $this->generate();

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Suggested text.', $result->text);
        $this->assertSame(10, $result->inputTokens);
        $this->assertSame(20, $result->outputTokens);
        $this->assertSame(30, $result->totalTokens);
        $this->assertNotNull($result->generationId);
    }

    public function test_a_provider_timeout_is_recorded_as_failed(): void
    {
        $this->fakeAiProvider()->willTimeOut();

        $result = $this->generate();

        $this->assertSame('failed', $result->status);
        $this->assertSame('timeout', $result->errorCode);
        $this->assertNull($result->text);
    }

    public function test_a_provider_error_is_recorded_as_failed(): void
    {
        $this->fakeAiProvider()->willFail();

        $result = $this->generate();

        $this->assertSame('failed', $result->status);
        $this->assertSame('provider_error', $result->errorCode);
    }

    public function test_an_empty_provider_response_is_recorded_as_failed(): void
    {
        $this->fakeAiProvider()->willReturnEmpty();

        $result = $this->generate();

        $this->assertSame('failed', $result->status);
        $this->assertSame('empty_response', $result->errorCode);
    }

    public function test_ai_disabled_refuses_without_calling_the_provider(): void
    {
        Config::set('ai.enabled', false);

        $result = $this->generate();

        $this->assertSame('failed', $result->status);
        $this->assertSame('ai_disabled', $result->errorCode);
        $this->assertSame(0, $this->fakeAiProvider()->callCount());
    }

    // ------------------------------------------------------------- schema

    public function test_ai_generations_stores_metadata_only_no_prompt_response_or_cost_columns(): void
    {
        $this->fakeAiProvider()->willSucceedWith('Text.');
        $result = $this->generate();

        $this->assertDatabaseHas('ai_generations', [
            'id' => $result->generationId,
            'use_case' => 'test_use_case',
            'provider' => 'fake',
            'model' => 'fake-model',
            'prompt_version' => 'v1',
            'status' => 'success',
        ]);

        $this->assertFalse(Schema::hasColumn('ai_generations', 'prompt'));
        $this->assertFalse(Schema::hasColumn('ai_generations', 'response'));
        $this->assertFalse(Schema::hasColumn('ai_generations', 'request_payload'));
        $this->assertFalse(Schema::hasColumn('ai_generations', 'estimated_cost'));
    }

    public function test_accepted_at_starts_null(): void
    {
        $this->fakeAiProvider()->willSucceedWith('Text.');
        $result = $this->generate();

        $generation = AiGeneration::findOrFail($result->generationId);

        $this->assertNull($generation->accepted_at);
        $this->assertFalse($generation->isAccepted());
    }

    public function test_mark_accepted_sets_accepted_at(): void
    {
        $this->fakeAiProvider()->willSucceedWith('Text.');
        $result = $this->generate();

        app(AiGenerationService::class)->markAccepted($result->generationId);

        $this->assertNotNull(AiGeneration::findOrFail($result->generationId)->accepted_at);
    }

    // -------------------------------------------------------- permissions

    public function test_seeded_role_grants_match_the_approved_v9a_scope(): void
    {
        $this->assertTrue(Role::where('name', 'principal')->first()->hasPermissionTo('ai.use'));
        $this->assertTrue(Role::where('name', 'teacher')->first()->hasPermissionTo('ai.use'));
        $this->assertFalse(Role::where('name', 'management')->first()->hasPermissionTo('ai.use'));
        $this->assertFalse(Role::where('name', 'admin_staff')->first()->hasPermissionTo('ai.use'));
        $this->assertTrue(Role::where('name', 'super_admin')->first()->hasPermissionTo('ai.use'));
    }

    public function test_a_genuinely_fresh_seed_grants_ai_use_correctly_with_no_manual_step(): void
    {
        // seedCommunicationReferenceData-style: re-run the seeder cold, exactly
        // as a fresh install would, and confirm the grant needs nothing extra.
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->assertTrue(\Spatie\Permission\Models\Permission::where('name', 'ai.use')->exists());
    }

    // -------------------------------------------------------- rate limits

    public function test_the_sixth_generation_within_a_minute_is_refused_without_calling_the_provider(): void
    {
        Config::set('ai.rate_limit.per_minute', 5);
        $this->fakeAiProvider()->willSucceedWith('Text.');
        $user = $this->userWithPermission();

        for ($i = 0; $i < 5; $i++) {
            $this->generateAs($user);
        }
        $this->assertSame(5, $this->fakeAiProvider()->callCount());

        $result = $this->generateAs($user);

        $this->assertSame('rate_limited', $result->status);
        $this->assertSame(5, $this->fakeAiProvider()->callCount());
    }

    public function test_the_daily_cap_refuses_further_generations_without_calling_the_provider(): void
    {
        Config::set('ai.rate_limit.per_minute', 1000); // isolate the daily-cap path
        Config::set('ai.rate_limit.per_day', 3);
        $this->fakeAiProvider()->willSucceedWith('Text.');
        $user = $this->userWithPermission();

        for ($i = 0; $i < 3; $i++) {
            $this->generateAs($user);
        }
        $this->assertSame(3, $this->fakeAiProvider()->callCount());

        $result = $this->generateAs($user);

        $this->assertSame('rate_limited', $result->status);
        $this->assertSame(3, $this->fakeAiProvider()->callCount());
    }

    public function test_a_rate_limited_attempt_does_not_itself_count_toward_the_daily_cap(): void
    {
        Config::set('ai.rate_limit.per_minute', 1);
        Config::set('ai.rate_limit.per_day', 50);
        $this->fakeAiProvider()->willSucceedWith('Text.');
        $user = $this->userWithPermission();

        $this->generateAs($user); // consumes the per-minute allowance
        $this->generateAs($user); // rate_limited, logged, but must not count toward the daily cap

        $successCountToday = AiGeneration::where('user_id', $user->id)
            ->whereIn('status', ['success', 'failed'])
            ->whereDate('created_at', now()->toDateString())
            ->count();

        $this->assertSame(1, $successCountToday);
    }

    // ------------------------------------------------------------ helpers

    private function generate(): \App\Ai\AiGenerationResult
    {
        return $this->generateAs($this->userWithPermission());
    }

    private function generateAs(User $user): \App\Ai\AiGenerationResult
    {
        return app(AiGenerationService::class)->generate(
            $user,
            'test_use_case',
            'v1',
            new AiGenerationRequest('System.', 'User content.', 0.5, 500),
        );
    }

    private function userWithPermission(): User
    {
        $user = User::factory()->create();
        $user->assignRole('principal');

        return $user;
    }
}
