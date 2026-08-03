<?php

declare(strict_types=1);

use App\Enums\OperationalDailyDigestStatusEnum;
use App\Models\OperationalDailyDigest;
use App\Models\User;
use App\Notifications\OperationalDailyDigestSlackNotification;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Notification;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Typer;

\test('admin can retry only a failed digest using current Slack configuration', function (): void {
    Notification::fake();
    Config::inject()->assign('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne([
        'company_slack_channel' => '#company-operations',
    ]), User::class);
    $failed = OperationalDailyDigest::factory()->create([
        'company_user_id' => $admin->getKey(),
        'status' => OperationalDailyDigestStatusEnum::FAILED->value,
        'last_error' => 'Předchozí chyba.',
    ]);

    $response = $this->be($admin, 'users')->post('/settings/slack-digests/' . $failed->getKey() . '/retry');

    $response->assertRedirect('/settings/slack-digests/' . $failed->getKey());
    \expect($failed->fresh()?->getStatus())->toBe(OperationalDailyDigestStatusEnum::QUEUED)
        ->and($failed->fresh()?->getLastError())->toBeNull();
    Notification::assertSentOnDemandTimes(OperationalDailyDigestSlackNotification::class, 1);

    $sent = OperationalDailyDigest::factory()->create([
        'company_user_id' => $admin->getKey(),
        'digest_date' => '2026-08-01',
        'status' => OperationalDailyDigestStatusEnum::SENT->value,
    ]);
    $this->post('/settings/slack-digests/' . $sent->getKey() . '/retry')->assertStatus(422);
});
