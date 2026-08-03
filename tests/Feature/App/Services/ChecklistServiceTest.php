<?php

declare(strict_types=1);

use App\Enums\ChecklistShiftEnum;
use App\Enums\ChecklistTemplateScopeEnum;
use App\Models\ChecklistDay;
use App\Models\ChecklistItem;
use App\Models\ChecklistTemplateTask;
use App\Models\Store;
use App\Models\Worker;
use App\Notifications\OperationalActivitySlackNotification;
use App\Services\ChecklistService;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Thinkycz\LaravelCore\Support\Config;

\test('default checklist catalog creates exact daily and weekly task groups for retail store', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);

    $service = new ChecklistService();
    $service->initializeStore($store);
    $service->initializeStore($store);

    $dailyMorning = ChecklistTemplateTask::query()
        ->where('store_id', $store->getKey())
        ->where('scope', ChecklistTemplateScopeEnum::Daily->value)
        ->where('shift', ChecklistShiftEnum::Morning->value)
        ->orderBy('position')
        ->pluck('text')
        ->all();

    \expect($dailyMorning)->toBe([
        'Rozsvítit světla a reklamy',
        'Zapnout TV, kasu, horkou vodu, delivery + zapojit',
        'Odkrýt toppingy, vyleštit a doplnit',
        'Uvařit čaje, cukr, doplnit ovoce, kelímky, brčka…',
        'Uvařit tapioku (700 g)',
        'Otřít skla, plochy, kasu, lednice, celý stánek',
        'Spočítat kasu (depozit 1 500 Kč nebo 3 000 Kč)',
        'Vystavit + doplnit hračky',
    ]);

    \expect(ChecklistTemplateTask::query()->where('store_id', $store->getKey())->count())->toBe(53);
});

\test('daily checklist snapshot merges daily and weekday tasks and remains stable', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-03 08:00:00', 'Europe/Prague'));
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);
    $service = new ChecklistService();
    $service->initializeStore($store);

    $day = $service->ensureDay($store, CarbonImmutable::parse('2026-08-03', 'Europe/Prague'));
    $again = $service->ensureDay($store, CarbonImmutable::parse('2026-08-03', 'Europe/Prague'));

    \expect($again->getKey())->toBe($day->getKey())
        ->and($day->items()->where('shift', ChecklistShiftEnum::Morning->value)->count())->toBe(10)
        ->and($day->items()->where('shift', ChecklistShiftEnum::Afternoon->value)->count())->toBe(12);

    ChecklistTemplateTask::query()->where('store_id', $store->getKey())->delete();
    $service->initializeStore($store);

    \expect(ChecklistDay::query()->whereKey($day->getKey())->firstOrFail()->items()->count())->toBe(22)
        ->and(ChecklistTemplateTask::query()->where('store_id', $store->getKey())->count())->toBe(0);
});

\test('warehouse does not receive checklist templates or a daily snapshot', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $service = new ChecklistService();

    $service->initializeStore($warehouse);

    \expect(ChecklistTemplateTask::query()->where('store_id', $warehouse->getKey())->count())->toBe(0);
    $service->ensureDay($warehouse, CarbonImmutable::now('Europe/Prague'));
})->throws(InvalidArgumentException::class);

\test('checklist notifies only aggregate shift completion and reopening transitions', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-03 09:00:00', 'Europe/Prague'));
    Notification::fake();
    Config::inject()->assign('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    $admin = UserFactory::new()->admin()->createOne();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false, 'slack_channel' => '#praha']);
    $actor = UserFactory::new()->limited($store)->createOne();
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $day = ChecklistDay::factory()->create(['user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'date' => '2026-08-03']);
    $first = ChecklistItem::factory()->create(['checklist_day_id' => $day->getKey(), 'shift' => ChecklistShiftEnum::Morning->value, 'position' => 1]);
    $second = ChecklistItem::factory()->create(['checklist_day_id' => $day->getKey(), 'shift' => ChecklistShiftEnum::Morning->value, 'position' => 2]);
    $service = new ChecklistService();

    $service->updateItem($first, $store, $actor, $worker, true, 1);
    Notification::assertNothingSent();

    $service->updateItem($second, $store, $actor, $worker, true, 1);
    Notification::assertSentOnDemandTimes(OperationalActivitySlackNotification::class, 1);

    $service->updateItem($first->refresh(), $store, $actor, $worker, false, 2);
    Notification::assertSentOnDemandTimes(OperationalActivitySlackNotification::class, 2);

    $service->updateItem($second->refresh(), $store, $actor, $worker, false, 2);
    Notification::assertSentOnDemandTimes(OperationalActivitySlackNotification::class, 2);
});

\test('checklist day excuse lifecycle sends two store notifications without reasons', function (): void {
    Notification::fake();
    Config::inject()->assign('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    $admin = UserFactory::new()->admin()->createOne();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false, 'slack_channel' => '#praha']);
    $day = ChecklistDay::factory()->create(['user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'date' => '2026-08-03']);
    $service = new ChecklistService();

    $service->excuseDay($day, $admin, 'Státní svátek', true);
    $service->excuseDay($day, $admin, 'Chybná omluva', false);

    Notification::assertSentOnDemandTimes(OperationalActivitySlackNotification::class, 2);
    Notification::assertSentOnDemand(
        OperationalActivitySlackNotification::class,
        static function (OperationalActivitySlackNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
            $payload = \json_encode($notification->toSlack($notifiable)->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);

            return $notifiable->routeNotificationFor('slack') === '#praha' &&
                !\str_contains($payload, 'Státní svátek') &&
                !\str_contains($payload, 'Chybná omluva');
        },
    );
});
