<?php

declare(strict_types=1);

use App\Models\ShiftRequest;
use App\Models\ShiftRequestMonthLock;
use App\Models\ShiftShareLink;
use App\Models\Store;
use App\Models\Worker;
use App\Notifications\OperationalActivitySlackNotification;
use Database\Factories\UserFactory;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Thinkycz\LaravelCore\Support\Config;

\test('public request toggle creates replaces and removes one daily request', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    ShiftShareLink::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'token' => 'requests-token',
    ]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    Carbon::setTestNow('2026-08-07 10:00:00 UTC');
    $url = '/public/shifts/requests-token/requests/toggle';

    $this->postJson($url, [
        'worker_id' => $worker->getKey(), 'date' => '2026-09-10', 'start_time' => '09:00', 'end_time' => '17:00',
    ])->assertCreated()->assertJsonPath('status', 'created')->assertJsonPath('request.start_time', '09:00');

    $this->postJson($url, [
        'worker_id' => $worker->getKey(), 'date' => '2026-09-10', 'start_time' => '10:00', 'end_time' => '18:00',
    ])->assertOk()->assertJsonPath('status', 'updated')->assertJsonPath('request.start_time', '10:00');
    \expect(ShiftRequest::query()->count())->toBe(1);

    $this->postJson($url, [
        'worker_id' => $worker->getKey(), 'date' => '2026-09-10', 'start_time' => '10:00', 'end_time' => '18:00',
    ])->assertOk()->assertJsonPath('status', 'deleted')->assertJsonPath('request', null);
    \expect(ShiftRequest::query()->count())->toBe(0);

    Carbon::setTestNow();
});

\test('public request create and update notify the store Slack channel', function (): void {
    Notification::fake();
    Config::inject()->assign('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'name' => 'Praha',
        'is_warehouse' => false,
        'slack_channel' => '#praha',
    ]);
    ShiftShareLink::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'token' => 'requests-token',
    ]);
    $worker = Worker::factory()->create([
        'user_id' => $admin->getKey(), 'first_name' => 'Jan', 'last_name' => 'Novák',
    ]);
    Carbon::setTestNow('2026-08-07 10:00:00 UTC');
    $url = '/public/shifts/requests-token/requests/toggle';

    $this->postJson($url, [
        'worker_id' => $worker->getKey(), 'date' => '2026-09-10', 'start_time' => '09:00', 'end_time' => '17:00',
    ])->assertCreated();
    $this->postJson($url, [
        'worker_id' => $worker->getKey(), 'date' => '2026-09-10', 'start_time' => '10:00', 'end_time' => '18:00',
    ])->assertOk();

    Notification::assertSentOnDemandTimes(OperationalActivitySlackNotification::class, 2);
    Notification::assertSentOnDemand(
        OperationalActivitySlackNotification::class,
        static function (OperationalActivitySlackNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
            $payload = \json_encode($notification->toSlack($notifiable)->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);

            return $channels === ['slack'] &&
                $notifiable->routeNotificationFor('slack') === '#praha' &&
                \str_contains($payload, 'Jan Novák') &&
                \str_contains($payload, '10. 9. 2026') &&
                \str_contains($payload, '10:00–18:00');
        },
    );

    $this->postJson($url, [
        'worker_id' => $worker->getKey(), 'date' => '2026-09-10', 'start_time' => '10:00', 'end_time' => '18:00',
    ])->assertOk()->assertJsonPath('status', 'deleted');
    Notification::assertSentOnDemandTimes(OperationalActivitySlackNotification::class, 2);

    Carbon::setTestNow();
});

\test('public request toggle rejects current months locked months and foreign workers', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    ShiftShareLink::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'token' => 'requests-token',
    ]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $foreignAdmin = UserFactory::new()->admin()->createOne();
    $foreignWorker = Worker::factory()->create(['user_id' => $foreignAdmin->getKey()]);
    Carbon::setTestNow('2026-08-07 10:00:00 UTC');
    $url = '/public/shifts/requests-token/requests/toggle';

    $this->postJson($url, [
        'worker_id' => $worker->getKey(), 'date' => '2026-08-10', 'start_time' => '09:00', 'end_time' => '17:00',
    ])->assertUnprocessable()->assertJsonValidationErrors('date');

    ShiftRequestMonthLock::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'year' => 2026, 'month' => 9,
        'locked_by_user_id' => $admin->getKey(),
    ]);
    $this->postJson($url, [
        'worker_id' => $worker->getKey(), 'date' => '2026-09-10', 'start_time' => '09:00', 'end_time' => '17:00',
    ])->assertUnprocessable()->assertJsonValidationErrors('date');

    $this->postJson($url, [
        'worker_id' => $foreignWorker->getKey(), 'date' => '2026-10-10', 'start_time' => '09:00', 'end_time' => '17:00',
    ])->assertUnprocessable()->assertJsonValidationErrors('worker_id');

    Carbon::setTestNow();
});

\test('unknown public request toggle token returns not found', function (): void {
    $this->postJson('/public/shifts/unknown/requests/toggle', [])->assertNotFound();
});
