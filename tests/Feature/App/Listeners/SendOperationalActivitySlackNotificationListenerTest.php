<?php

declare(strict_types=1);

use App\Enums\OperationalActivityTypeEnum;
use App\Events\OperationalActivityEvent;
use App\Listeners\SendOperationalActivitySlackNotificationListener;
use App\Models\Store;
use App\Notifications\OperationalActivitySlackNotification;
use App\Services\OperationalActivityService;
use Illuminate\Contracts\Notifications\Factory as NotificationFactoryContract;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Thinkycz\LaravelCore\Support\Config;

\test('listener routes one queued notification to each distinct configured store channel', function (): void {
    Notification::fake();
    Config::inject()->assign('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');

    (new SendOperationalActivitySlackNotificationListener())->handle(new OperationalActivityEvent(
        OperationalActivityTypeEnum::STOCK_TRANSFER_CREATED,
        'operator@example.com',
        '2026-07-22T10:15:00+00:00',
        '/stock-movements/42',
        [
            ['channel' => '#praha', 'store' => 'Praha', 'perspective' => 'outgoing'],
            ['channel' => '#brno', 'store' => 'Brno', 'perspective' => 'incoming'],
            ['channel' => '#praha', 'store' => 'Praha duplicate', 'perspective' => 'outgoing'],
        ],
        ['Slack movement number' => 'P-2026-0042', 'Slack item count' => '3'],
    ));

    Notification::assertSentOnDemandTimes(OperationalActivitySlackNotification::class, 2);
    Notification::assertSentOnDemand(
        OperationalActivitySlackNotification::class,
        static fn(OperationalActivitySlackNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $channels === ['slack'] && $notifiable->routeNotificationFor('slack') === '#praha' && $notification->getStoreName() === 'Praha',
    );
    Notification::assertSentOnDemand(
        OperationalActivitySlackNotification::class,
        static fn(OperationalActivitySlackNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $channels === ['slack'] && $notifiable->routeNotificationFor('slack') === '#brno' && $notification->getStoreName() === 'Brno',
    );
});

\test('listener remains silent without a bot token', function (): void {
    Notification::fake();
    Config::inject()->assign('services.slack.notifications.bot_user_oauth_token', null);

    (new SendOperationalActivitySlackNotificationListener())->handle(new OperationalActivityEvent(
        OperationalActivityTypeEnum::STATEMENT_SAVED,
        'operator@example.com',
        '2026-07-22T10:15:00+00:00',
        '/statements',
        [['channel' => '#praha', 'store' => 'Praha', 'perspective' => null]],
        [],
    ));

    Notification::assertNothingSent();
});

\test('rolled back activities do not send notifications', function (): void {
    Notification::fake();
    Config::inject()->assign('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey(), 'slack_channel' => '#praha']);

    try {
        DB::transaction(static function () use ($user, $store): void {
            OperationalActivityService::dispatch(
                OperationalActivityTypeEnum::STATEMENT_SAVED,
                $user,
                '2026-07-22T10:15:00+00:00',
                '/statements',
                [['store' => $store, 'perspective' => null]],
                [],
            );

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
        // Expected rollback.
    }

    Notification::assertNothingSent();
});

\test('notification enqueue failures are reported without escaping the listener', function (): void {
    Config::inject()->assign('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    Notification::swap(new class implements NotificationFactoryContract {
        /**
         * @inheritDoc
         */
        public function channel($name = null): mixed { return null; }

        /**
         * @inheritDoc
         */
        public function send($notifiables, $notification): never { throw new RuntimeException('queue unavailable'); }

        /**
         * @inheritDoc
         */
        public function sendNow($notifiables, $notification): never { throw new RuntimeException('queue unavailable'); }
    });

    \expect(static function (): void {
        (new SendOperationalActivitySlackNotificationListener())->handle(new OperationalActivityEvent(
            OperationalActivityTypeEnum::STATEMENT_SAVED,
            'operator@example.com',
            '2026-07-22T10:15:00+00:00',
            '/statements',
            [['channel' => '#praha', 'store' => 'Praha', 'perspective' => null]],
            [],
        ));
    })->not->toThrow(RuntimeException::class);
});
