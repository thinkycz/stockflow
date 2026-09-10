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
        ->toContain('22.7.2026 12:15')
        ->toContain('Otevřít ve StockFlow');
});

\test('company notification omits store context', function (): void {
    $notification = new OperationalActivitySlackNotification(
        OperationalActivityTypeEnum::STATEMENT_SAVED,
        'admin@example.com',
        null,
        null,
        '2026-08-02T10:15:00+00:00',
        ['Slack month' => '2026-08'],
        'https://stockflow.test/reports',
    );

    $encoded = \json_encode(
        $notification->toSlack(new AnonymousNotifiable())->toArray(),
        flags: \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE,
    );

    \expect($notification->getStoreName())->toBeNull()
        ->and($encoded)->not->toContain('Prodejna')
        ->toContain('admin@example.com')
        ->toContain('2026-08');
});

\test('every operational type has translated renderable Slack and digest labels', function (OperationalActivityTypeEnum $type): void {
    foreach (['en', 'cs', 'sk'] as $locale) {
        \expect(\__($type->translationKey(), [], $locale))->not->toBe($type->translationKey());
    }
    $notification = new OperationalActivitySlackNotification(
        $type,
        'admin@example.com',
        'Store',
        null,
        '2026-09-10T10:00:00Z',
        ['Slack amount' => '100,00 Kč'],
        'https://stockflow.test/dashboard',
    );
    \expect($notification->toSlack(new AnonymousNotifiable())->toArray()['blocks'][0]['text']['text'])->toBe(\__($type->translationKey(), [], 'cs'))
        ->and($type->digestLabel())->not->toBeEmpty()
        ->and($type->digestCategory())->not->toBeEmpty();
})->with(OperationalActivityTypeEnum::cases());

\test('long user supplied values remain escaped and bounded in Slack fields', function (): void {
    $notification = new OperationalActivitySlackNotification(
        OperationalActivityTypeEnum::FINANCIAL_ROW_CREATED,
        'admin@example.com',
        'Store',
        null,
        '2026-09-10T10:00:00Z',
        ['Slack entry' => \str_repeat('<!channel>&', 1000)],
        'https://stockflow.test/income-expenses',
    );
    $payload = $notification->toSlack(new AnonymousNotifiable())->toArray();
    foreach ($payload['blocks'] as $block) {
        foreach ($block['fields'] ?? [] as $field) {
            \expect(\mb_strlen($field['text']))->toBeLessThanOrEqual(2000)
                ->and($field['text'])->not->toContain('<!channel>');
        }
    }
});
