<?php

declare(strict_types=1);

use App\Domain\OperationalActivity\DailyOperationalDigestService;
use App\Enums\OperationalDailyDigestStatusEnum;
use App\Jobs\PruneOperationalDigestHistoryJob;
use App\Models\OperationalDailyDigest;
use App\Notifications\OperationalDailyDigestSlackNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Typer;

\afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

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

\test('generation never recreates pruned dates after 120 business days and reaches yesterday', function (): void {
    Notification::fake();
    Config::inject()->assign('services.slack.notifications.bot_user_oauth_token', null);
    $now = CarbonImmutable::parse('2026-09-04 22:15:00', 'UTC');
    CarbonImmutable::setTestNow($now);
    [$admin] = \createIsolatedUserWithWarehouse();
    $today = $now->setTimezone('Europe/Prague')->startOfDay();
    $admin->update(['operational_digest_started_on' => $today->subDays(120)->toDateString()]);
    for ($daysAgo = 120; $daysAgo >= 2; --$daysAgo) {
        OperationalDailyDigest::factory()->create([
            'company_user_id' => $admin->getKey(),
            'digest_date' => $today->subDays($daysAgo)->toDateString(),
        ]);
    }
    (new PruneOperationalDigestHistoryJob())->handle();
    $service = new DailyOperationalDigestService();
    $digest = $service->createNext($admin, $now);
    \expect($digest?->getDigestDate()->toDateString())->toBe($today->subDay()->toDateString())

        ->and(OperationalDailyDigest::query()->count())->toBe(90)
        ->and(OperationalDailyDigest::query()->whereDate('digest_date', '<', $today->subDays(90)->toDateString())->exists())->toBeFalse();
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $next = $service->createNext($admin, $now);
        $queries = \count(DB::getQueryLog());
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
    \expect($next)->toBeNull()->and($queries)->toBe(1);
});

\test('retention cutoff itself remains eligible for generation', function (): void {
    Notification::fake();
    Config::inject()->assign('services.slack.notifications.bot_user_oauth_token', null);
    [$admin] = \createIsolatedUserWithWarehouse();
    $admin->update(['operational_digest_started_on' => '2025-01-01']);
    $now = CarbonImmutable::parse('2026-09-04 22:15:00', 'UTC');
    \expect((new DailyOperationalDigestService())->createNext($admin, $now)?->getDigestDate()->toDateString())
        ->toBe($now->setTimezone('Europe/Prague')->subDays(90)->toDateString());
});
