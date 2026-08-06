<?php

declare(strict_types=1);

use App\Enums\OperationalDailyDigestStatusEnum;
use App\Listeners\MarkOperationalDailyDigestSlackSentListener;
use App\Models\OperationalDailyDigest;
use App\Notifications\OperationalDailyDigestSlackNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationSent;

\test('daily digest builds one Czech text message with locations and archive link', function (): void {
    $digest = OperationalDailyDigest::factory()->create([
        'snapshot' => [
            'date' => '2026-08-02',
            'title' => 'Denní provozní souhrn — 2. srpna 2026',
            'intro' => 'Za tento den byly zaznamenány 3 provozní milníky.',
            'period_start' => '2026-08-01T22:00:00+00:00',
            'period_end' => '2026-08-02T22:00:00+00:00',
            'activity_count' => 3,
            'sections' => [[
                'key' => 'store:1',
                'name' => 'Praha & centrum',
                'is_warehouse' => false,
                'activity_count' => 3,
                'paragraphs' => [
                    'Docházka: 2× příchod; 1× odchod.',
                    'Finance za 08/2026: příjmy 12 000,00 Kč; výdaje 8 000,00 Kč; zisk 4 000,00 Kč.',
                    'Výkaz za 02. 08. 2026: celkem 5 000,00 Kč.',
                ],
                'details' => [],
            ]],
        ],
        'activity_count' => 3,
    ]);
    $notification = new OperationalDailyDigestSlackNotification($digest->getKey());

    $payload = $notification->toSlack(new AnonymousNotifiable())->toArray();
    $encoded = \json_encode($payload, flags: \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

    \expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->tries)->toBe(5)
        ->and($notification->backoff())->toBe([60, 300, 1800, 18000])
        ->and($encoded)->toContain('Denní provozní souhrn')
        ->toContain('🏪 Praha &amp; centrum')
        ->toContain('• *Docházka:*')
        ->toContain('2× příchod')
        ->toContain('*Finance za 08/2026:*')
        ->toContain('příjmy 12 000,00 Kč')
        ->toContain('zisk 4 000,00 Kč')
        ->toContain('*Výkaz za 02. 08. 2026:* celkem 5 000,00 Kč')
        ->toContain('slack-digests')
        ->and($digest->fresh()?->getAttemptCount())->toBe(1);
});

\test('daily digest records successful and exhausted delivery states without leaking exception details', function (): void {
    $sent = OperationalDailyDigest::factory()->create(['status' => OperationalDailyDigestStatusEnum::QUEUED->value]);
    $sentNotification = new OperationalDailyDigestSlackNotification($sent->getKey());
    (new MarkOperationalDailyDigestSlackSentListener())->handle(new NotificationSent(
        new AnonymousNotifiable(),
        $sentNotification,
        'slack',
    ));

    $failed = OperationalDailyDigest::factory()->create([
        'digest_date' => '2026-08-01',
        'status' => OperationalDailyDigestStatusEnum::QUEUED->value,
    ]);
    $failedNotification = new OperationalDailyDigestSlackNotification($failed->getKey());
    $failedNotification->failed(new RuntimeException('xoxb-super-secret transport response'));

    \expect($sent->fresh()?->getStatus())->toBe(OperationalDailyDigestStatusEnum::SENT)
        ->and($sent->fresh()?->getSentAt())->not->toBeNull()
        ->and($failed->fresh()?->getStatus())->toBe(OperationalDailyDigestStatusEnum::FAILED)
        ->and($failed->fresh()?->getLastError())->toBe('Slack doručení selhalo po vyčerpání retry.')
        ->and($failed->fresh()?->getLastError())->not->toContain('xoxb-super-secret');
});
