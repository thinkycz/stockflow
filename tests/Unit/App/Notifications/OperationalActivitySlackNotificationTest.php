<?php

declare(strict_types=1);

use App\Enums\OperationalActivityTypeEnum;
use App\Events\OperationalActivityEvent;
use App\Notifications\OperationalActivitySlackNotification;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;

\test('operational activity uses post-commit events and queued notifications', function (): void {
    $event = new OperationalActivityEvent(
        OperationalActivityTypeEnum::STATEMENT_SAVED,
        'operator@example.com',
        '2026-07-22T10:15:00+00:00',
        '/statements',
        [['channel' => '#praha', 'store' => 'Praha', 'perspective' => null]],
        [],
    );
    $notification = new OperationalActivitySlackNotification(
        $event->type,
        $event->actorEmail,
        'Praha',
        null,
        $event->occurredAt,
        $event->facts,
        $event->url,
    );

    \expect($event)->toBeInstanceOf(ShouldDispatchAfterCommit::class)
        ->and($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->afterCommit)->toBeTrue();
});

\test('notification builds Czech Block Kit content with Prague time and escaped scalar facts', function (): void {
    $notification = new OperationalActivitySlackNotification(
        OperationalActivityTypeEnum::ATTENDANCE_ARRIVAL,
        'limited@example.com',
        'Praha <centrum>',
        null,
        '2026-07-22T10:15:00+00:00',
        ['Slack worker' => 'Jan & Petr'],
        'https://stockflow.test/attendance',
    );

    $payload = $notification->toSlack(new AnonymousNotifiable())->toArray();
    $encoded = \json_encode($payload, flags: \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);

    \expect($encoded)->toContain('Příchod zaznamenán')
        ->toContain('Praha &lt;centrum&gt;')
        ->toContain('Jan &amp; Petr')
        ->toContain('22. 7. 2026 12:15')
        ->toContain('Otevřít ve StockFlow');
});
