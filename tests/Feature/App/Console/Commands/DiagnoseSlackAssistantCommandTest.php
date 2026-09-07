<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Thinkycz\LaravelCore\Support\Config;

\test('Slack diagnostics fail closed when disabled and never send messages', function (): void {
    Http::preventStrayRequests();
    Http::fake();
    Config::inject()->assign('services.slack.assistant.enabled', false);
    $this->artisan('stockflow:slack-assistant:diagnose')->assertExitCode(1);
    Http::assertNothingSent();
});

\test('Slack live diagnostics inspect identity and both channels without posting', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    foreach (['ai.assistant.enabled' => true, 'services.slack.assistant.enabled' => true, 'services.slack.assistant.admin_user_id' => $admin->getKey(), 'services.slack.assistant.workspace_id' => 'T1', 'services.slack.assistant.signing_secret' => 'secret', 'services.slack.assistant.bot_user_id' => 'UBOT', 'services.slack.notifications.bot_user_oauth_token' => 'test-token'] as $key => $value) {
        Config::inject()->assign($key, $value);
    }
    Http::preventStrayRequests();
    Http::fake([
        'slack.com/api/auth.test' => Http::response(['ok' => true, 'team_id' => 'T1', 'user_id' => 'UBOT']),
        'slack.com/api/conversations.info' => Http::response(['ok' => true, 'channel' => ['is_member' => true]]),
        'slack.com/api/conversations.history' => Http::response(['ok' => true, 'messages' => [['ts' => '100.000001']]]),
        'slack.com/api/conversations.replies' => Http::response(['ok' => true, 'messages' => [['ts' => '100.000001']]]),
    ]);
    $this->artisan('stockflow:slack-assistant:diagnose', ['--live' => true, '--channel' => ['CSTORE', 'CGENERAL']])->assertExitCode(0);
    Http::assertSentCount(7);
    Http::assertNotSent(fn($request): bool => \str_ends_with($request->url(), 'chat.postMessage'));
});
