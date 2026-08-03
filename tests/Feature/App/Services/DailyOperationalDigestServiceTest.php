<?php

declare(strict_types=1);

use App\Enums\OperationalDailyDigestStatusEnum;
use App\Models\OperationalDailyDigest;
use App\Notifications\OperationalDailyDigestSlackNotification;
use App\Services\DailyOperationalDigestService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Typer;

\test('service creates the oldest missing digest and records missing Slack configuration as failed', function (): void {
    Notification::fake();
    Config::inject()->assign('services.slack.notifications.bot_user_oauth_token', null);
    [$admin] = \createIsolatedUserWithWarehouse();
    $admin->update(['operational_digest_started_on' => '2026-08-01']);

    $service = new DailyOperationalDigestService();
    $first = Typer::assertInstance($service->createNext($admin, CarbonImmutable::parse('2026-08-03 07:00', 'Europe/Prague')), OperationalDailyDigest::class);
    $second = Typer::assertInstance($service->createNext($admin, CarbonImmutable::parse('2026-08-03 08:00', 'Europe/Prague')), OperationalDailyDigest::class);
    $none = $service->createNext($admin, CarbonImmutable::parse('2026-08-03 09:00', 'Europe/Prague'));

    \expect($first->getDigestDate()->toDateString())->toBe('2026-08-01')
        ->and($second->getDigestDate()->toDateString())->toBe('2026-08-02')
        ->and($first->getStatus())->toBe(OperationalDailyDigestStatusEnum::FAILED)
        ->and($first->getLastError())->toBe('Chybí Slack bot token.')
        ->and($none)->toBeNull()
        ->and(OperationalDailyDigest::query()->count())->toBe(2);
    Notification::assertNothingSent();
});

\test('configured digest is queued once to the current company channel', function (): void {
    Notification::fake();
    Config::inject()->assign('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    [$admin] = \createIsolatedUserWithWarehouse();
    $admin->update([
        'company_slack_channel' => '#company-operations',
        'operational_digest_started_on' => '2026-08-02',
    ]);

    $digest = Typer::assertInstance(
        (new DailyOperationalDigestService())->createNext($admin, CarbonImmutable::parse('2026-08-03 07:00', 'Europe/Prague')),
        OperationalDailyDigest::class,
    );

    \expect($digest->fresh()?->getStatus())->toBe(OperationalDailyDigestStatusEnum::QUEUED);
    Notification::assertSentOnDemandTimes(OperationalDailyDigestSlackNotification::class, 1);
});
