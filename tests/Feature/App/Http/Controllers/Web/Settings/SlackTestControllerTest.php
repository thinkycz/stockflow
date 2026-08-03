<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\SlackTestNotification;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Notification;
use Thinkycz\LaravelCore\Support\Typer;

\test('admin can send a test Slack notification when a channel is configured', function (): void {
    Notification::fake();
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne([
        'company_slack_channel' => '#company-operations',
    ]), User::class);

    $response = $this->be($admin, 'users')->post('/settings/slack/test');

    $response->assertRedirect('/settings');
    Notification::assertSentOnDemandTimes(SlackTestNotification::class, 1);
});

\test('admin gets an error when sending a test Slack notification without a configured channel', function (): void {
    Notification::fake();
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne([
        'company_slack_channel' => null,
    ]), User::class);

    $response = $this->be($admin, 'users')->post('/settings/slack/test');

    $response->assertRedirect('/settings');
    Notification::assertNothingSent();
});

\test('non-admin cannot send a test Slack notification', function (): void {
    Notification::fake();
    $user = Typer::assertInstance(UserFactory::new()->createOne([
        'company_slack_channel' => '#company-operations',
    ]), User::class);

    $this->be($user, 'users')->post('/settings/slack/test')->assertRedirect('/dashboard');
    Notification::assertNothingSent();
});
