<?php

declare(strict_types=1);

use App\Jobs\RecordAssistantQueueHeartbeatJob;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

\test('assistant diagnose verifies the durable production boundary without exposing secrets', function (): void {
    Config::inject()->assign('ai.assistant.enabled', true);
    Config::inject()->assign('ai.providers.openrouter.key', 'diagnostic-secret');
    Config::inject()->assign('ai.assistant.lock_store', 'array');
    (new RecordAssistantQueueHeartbeatJob())->handle();

    $this->artisan('stockflow:assistant:diagnose')
        ->expectsOutputToContain('[OK] Provider is OpenRouter')
        ->expectsOutputToContain('[OK] Assistant queue heartbeat')
        ->doesntExpectOutputToContain('diagnostic-secret')
        ->assertSuccessful();
});

\test('assistant diagnose can run a tenant scoped read only smoke test', function (): void {
    Config::inject()->assign('ai.assistant.enabled', true);
    Config::inject()->assign('ai.providers.openrouter.key', 'diagnostic-secret');
    Config::inject()->assign('ai.assistant.lock_store', 'array');
    (new RecordAssistantQueueHeartbeatJob())->handle();
    Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);

    $this->artisan('stockflow:assistant:diagnose', ['--live' => true])
        ->expectsOutputToContain('[OK] Read-only smoke')
        ->assertSuccessful();

    \expect(Resolver::resolveCacheManager()->store('array')->has('assistant:queue:heartbeat'))->toBeTrue();
});
