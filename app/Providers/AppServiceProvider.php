<?php

namespace App\Providers;

use App\Ai\AiProvider;
use App\Ai\AnthropicAiProvider;
use App\Ai\DeepSeekAiProvider;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Exactly one active provider at a time, chosen by AI_PROVIDER
        // (config/ai.php) -- no automatic fallback between them. Tests bind
        // FakeAiProvider instead -- see TestCase -- so a real network call
        // never runs in the automated suite regardless of this switch.
        $this->app->bind(AiProvider::class, fn () => match (config('ai.provider')) {
            'deepseek' => new DeepSeekAiProvider(
                apiKey: config('services.deepseek.key'),
                model: config('ai.model'),
                timeoutSeconds: config('ai.timeout'),
            ),
            default => new AnthropicAiProvider(
                apiKey: config('services.anthropic.key'),
                model: config('ai.model'),
                timeoutSeconds: config('ai.timeout'),
            ),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });
    }
}
