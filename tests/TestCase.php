<?php

namespace Tests;

use App\Ai\AiProvider;
use App\Ai\FakeAiProvider;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Every test gets FakeAiProvider bound in place of the real adapter --
     * a real network call must never run in the automated suite (V9A
     * architecture review). Call fakeAiProvider() to configure or inspect it.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(AiProvider::class, new FakeAiProvider);
    }

    protected function fakeAiProvider(): FakeAiProvider
    {
        return $this->app->make(AiProvider::class);
    }
}
