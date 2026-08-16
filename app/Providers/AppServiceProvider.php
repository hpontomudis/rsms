<?php

namespace App\Providers;

use App\Ai\AiProvider;
use App\Ai\AnthropicAiProvider;
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
        // The one real provider adapter (V9A). Tests bind FakeAiProvider
        // instead -- see TestCase -- so a real network call never runs in
        // the automated suite.
        $this->app->bind(AiProvider::class, fn () => new AnthropicAiProvider(
            apiKey: config('services.anthropic.key'),
            model: config('ai.model'),
            timeoutSeconds: config('ai.timeout'),
        ));
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
