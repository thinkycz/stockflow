<?php

declare(strict_types=1);

use App\Jobs\ProcessSlackEventJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Thinkycz\LaravelCore\Support\Config;

\beforeEach(function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    foreach (['ai.assistant.enabled' => true, 'services.slack.assistant.enabled' => true, 'services.slack.assistant.admin_user_id' => $admin->getKey(), 'services.slack.assistant.workspace_id' => 'TTEAM', 'services.slack.assistant.signing_secret' => 'test-secret', 'services.slack.assistant.bot_user_id' => 'UBOT', 'services.slack.notifications.bot_user_oauth_token' => 'test-token'] as $key => $value) {
        Config::inject()->assign($key, $value);
    }
    Queue::fake();
});

\test('signed challenge is acknowledged without queueing or contacting Slack', function (): void {
    $body = \json_encode(['type' => 'url_verification', 'challenge' => 'challenge'], \JSON_THROW_ON_ERROR);
    $ts = (string) \time();
    $this->call('POST', '/slack/events', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SLACK_REQUEST_TIMESTAMP' => $ts, 'HTTP_X_SLACK_SIGNATURE' => 'v0=' . \hash_hmac('sha256', 'v0:' . $ts . ':' . $body, 'test-secret')], $body)->assertOk()->assertJsonPath('challenge', 'challenge');
    Queue::assertNothingPushed();
});

\test('duplicate message and mention deliveries persist one event before acknowledgement', function (): void {
    foreach (['message', 'app_mention', 'message'] as $type) {
        $body = \json_encode(['type' => 'event_callback', 'team_id' => 'TTEAM', 'event' => ['type' => $type, 'user' => 'UALICE', 'channel' => 'CSTORE', 'ts' => '100.000001', 'text' => '<@UBOT> hello']], \JSON_THROW_ON_ERROR);
        $ts = (string) \time();
        $this->call('POST', '/slack/events', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SLACK_REQUEST_TIMESTAMP' => $ts, 'HTTP_X_SLACK_SIGNATURE' => 'v0=' . \hash_hmac('sha256', 'v0:' . $ts . ':' . $body, 'test-secret')], $body)->assertOk();
    }
    \expect(DB::table('assistant_slack_events')->count())->toBe(1);
    Queue::assertPushed(ProcessSlackEventJob::class);
});

\test('bad signatures old requests and other workspaces cannot enter the journal', function (string $team, int $age, string $secret, int $status): void {
    $body = \json_encode(['type' => 'event_callback', 'team_id' => $team, 'event' => ['type' => 'message']], \JSON_THROW_ON_ERROR);
    $ts = (string) (\time() - $age);
    $this->call('POST', '/slack/events', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SLACK_REQUEST_TIMESTAMP' => $ts, 'HTTP_X_SLACK_SIGNATURE' => 'v0=' . \hash_hmac('sha256', 'v0:' . $ts . ':' . $body, $secret)], $body)->assertStatus($status);
    \expect(DB::table('assistant_slack_events')->count())->toBe(0);
})->with([['TTEAM', 0, 'wrong', 401], ['TTEAM', 301, 'test-secret', 401], ['TOTHER', 0, 'test-secret', 403]]);

\test('DM bot edit and deletion events are acknowledged without action', function (array $event): void {
    $body = \json_encode(['type' => 'event_callback', 'team_id' => 'TTEAM', 'event' => [...['type' => 'message', 'channel' => 'CSTORE', 'user' => 'UALICE', 'ts' => '100.000001', 'text' => '<@UBOT> hello'], ...$event]], \JSON_THROW_ON_ERROR);
    $ts = (string) \time();
    $this->call('POST', '/slack/events', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SLACK_REQUEST_TIMESTAMP' => $ts, 'HTTP_X_SLACK_SIGNATURE' => 'v0=' . \hash_hmac('sha256', 'v0:' . $ts . ':' . $body, 'test-secret')], $body)->assertOk();
    \expect(DB::table('assistant_slack_events')->count())->toBe(0);
})->with([[['channel' => 'DPRIVATE']], [['user' => 'UBOT']], [['bot_id' => 'BOTHER']], [['subtype' => 'message_changed']], [['subtype' => 'message_deleted']]]);

\test('signed form interactivity is persisted and workspace checked', function (): void {
    $payload = ['type' => 'block_actions', 'team' => ['id' => 'TTEAM'], 'channel' => ['id' => 'CSTORE'], 'user' => ['id' => 'UBOB'], 'container' => ['message_ts' => '100.000003'], 'message' => ['thread_ts' => '100.000001'], 'actions' => [['value' => '{"action":"approve"}', 'action_ts' => '100.000004']]];
    $form = ['payload' => \json_encode($payload, \JSON_THROW_ON_ERROR)];
    $body = \http_build_query($form);
    $ts = (string) \time();
    $this->call('POST', '/slack/interactivity', $form, [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded', 'HTTP_X_SLACK_REQUEST_TIMESTAMP' => $ts, 'HTTP_X_SLACK_SIGNATURE' => 'v0=' . \hash_hmac('sha256', 'v0:' . $ts . ':' . $body, 'test-secret')], $body)->assertOk();
    \expect(DB::table('assistant_slack_events')->value('author_id'))->toBe('UBOB');
});
