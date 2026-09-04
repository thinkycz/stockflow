<?php

declare(strict_types=1);

use App\Domain\OperationalActivity\DailyOperationalDigestService;
use App\Jobs\CreateDailyOperationalDigestJob;
use App\Jobs\PruneOperationalDigestHistoryJob;
use App\Models\OperationalActivity;
use App\Models\OperationalDailyDigest;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Thinkycz\LaravelCore\Support\Config;

\test('creation job builds the oldest missing digest for the single company', function (): void {
    Notification::fake();
    Config::inject()->assign('services.slack.notifications.bot_user_oauth_token', null);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-03 07:00', 'Europe/Prague'));
    [$admin] = \createIsolatedUserWithWarehouse();
    $admin->update(['operational_digest_started_on' => '2026-08-01']);

    $job = new CreateDailyOperationalDigestJob();
    $job->handle(new DailyOperationalDigestService());

    \expect($job)->toBeInstanceOf(ShouldQueue::class)
        ->and(OperationalDailyDigest::query()->sole()->getDigestDate()->toDateString())->toBe('2026-08-01');
});

\test('prune job removes journal and digest history older than ninety Prague days', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-03 04:15', 'Europe/Prague'));
    [$admin] = \createIsolatedUserWithWarehouse();

    OperationalActivity::factory()->create([
        'company_user_id' => $admin->getKey(),
        'occurred_at' => '2026-05-04T21:59:59+00:00',
    ]);
    OperationalActivity::factory()->create([
        'company_user_id' => $admin->getKey(),
        'occurred_at' => '2026-05-04T22:00:00+00:00',
    ]);
    OperationalDailyDigest::factory()->create([
        'company_user_id' => $admin->getKey(),
        'digest_date' => '2026-05-04',
    ]);
    OperationalDailyDigest::factory()->create([
        'company_user_id' => $admin->getKey(),
        'digest_date' => '2026-05-05',
    ]);

    $job = new PruneOperationalDigestHistoryJob();
    $job->handle();

    \expect($job)->toBeInstanceOf(ShouldQueue::class)
        ->and(OperationalActivity::query()->count())->toBe(1)
        ->and(OperationalActivity::query()->sole()->getOccurredAt()->toIso8601String())->toBe('2026-05-04T22:00:00+00:00')
        ->and(OperationalDailyDigest::query()->count())->toBe(1)
        ->and(OperationalDailyDigest::query()->sole()->getDigestDate()->toDateString())->toBe('2026-05-05');
});
