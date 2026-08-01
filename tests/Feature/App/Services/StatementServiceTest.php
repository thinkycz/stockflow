<?php

declare(strict_types=1);

use App\Enums\StockMovementClassificationEnum;
use App\Models\InventorySession;
use App\Models\InventorySessionItem;
use App\Models\Item;
use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\StockMovement;
use App\Models\StockMovementItem;
use App\Models\Store;
use App\Notifications\OperationalActivitySlackNotification;
use App\Services\StatementService;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Thinkycz\LaravelCore\Support\Config;

\test('statement save clear and restore notify once while creation and snapshots stay silent', function (): void {
    Notification::fake();
    Config::inject()->assign('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey(), 'slack_channel' => '#praha']);
    $service = \app(StatementService::class);
    $statement = $service->findOrCreateForMonth($user, $store, 2026, 7);
    Notification::assertNothingSent();
    $day = $statement->getDays()->first();
    \assert($day instanceof StatementDay);

    $service->updateDays($statement, [[
        'date' => $day->getDate(),
        'cash' => 100,
        'card' => 50,
        'wolt' => 0,
        'bolt' => 0,
        'foodora' => 0,
    ]], $user);
    $version = $statement->versions()->orderByDesc('id')->firstOrFail();
    $service->clear($statement, $user);
    $service->restoreVersion($version, $user);

    Notification::assertSentOnDemandTimes(OperationalActivitySlackNotification::class, 3);
});

\test('statement Slack notification contains todays channels and total without monthly revenue', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-23 10:00:00', 'Europe/Prague'));
    Notification::fake();
    Config::inject()->assign('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey(), 'slack_channel' => '#praha']);
    $service = \app(StatementService::class);
    $statement = $service->findOrCreateForMonth($user, $store, 2026, 7);

    $service->updateDays($statement, [[
        'date' => '2026-07-23',
        'cash' => 100,
        'card' => 50,
        'wolt' => 30,
        'bolt' => 20,
        'bolt_cash' => 15,
        'foodora' => 10,
    ]], $user);

    Notification::assertSentOnDemand(
        OperationalActivitySlackNotification::class,
        static function (OperationalActivitySlackNotification $notification): bool {
            $payload = $notification->toSlack(new AnonymousNotifiable())->toArray();
            $encoded = \json_encode($payload, flags: \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

            return \str_contains($encoded, '23. 7. 2026') &&
                \str_contains($encoded, 'Hotovost') &&
                \str_contains($encoded, 'Karta') &&
                \str_contains($encoded, 'Wolt') &&
                \str_contains($encoded, 'Bolt') &&
                \str_contains($encoded, 'Bolt hotově') &&
                \str_contains($encoded, 'Foodora') &&
                \str_contains($encoded, 'Dnešní součet') &&
                !\str_contains($encoded, 'Celková tržba');
        },
    );

    Carbon::setTestNow();
});

\test('historical statement Slack notification does not fabricate todays values', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-23 10:00:00', 'Europe/Prague'));
    Notification::fake();
    Config::inject()->assign('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey(), 'slack_channel' => '#praha']);
    $service = \app(StatementService::class);
    $statement = $service->findOrCreateForMonth($user, $store, 2026, 6);

    $service->updateDays($statement, [[
        'date' => '2026-06-23',
        'cash' => 100,
        'card' => 0,
        'wolt' => 0,
        'bolt' => 0,
        'bolt_cash' => 0,
        'foodora' => 0,
    ]], $user);

    Notification::assertSentOnDemand(
        OperationalActivitySlackNotification::class,
        static function (OperationalActivitySlackNotification $notification): bool {
            $payload = $notification->toSlack(new AnonymousNotifiable())->toArray();
            $encoded = \json_encode($payload, flags: \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

            return \str_contains($encoded, '06/2026') &&
                !\str_contains($encoded, 'Dnešní součet') &&
                !\str_contains($encoded, 'Celková tržba');
        },
    );

    Carbon::setTestNow();
});

\test('findOrCreateForMonth creates statement and rows for a fresh month', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $service = \app(StatementService::class);

    $statement = $service->findOrCreateForMonth($user, $store, 2026, 2);

    \expect($statement->getDays()->count())->toBe(28);
    \expect($statement->getStoreId())->toBe($store->getKey());
    \expect($statement->getYear())->toBe(2026);
    \expect($statement->getMonth())->toBe(2);
});

\test('findOrCreateForMonth reuses an existing statement', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $service = \app(StatementService::class);

    $first = $service->findOrCreateForMonth($user, $store, 2026, 6);
    $second = $service->findOrCreateForMonth($user, $store, 2026, 6);

    \expect($second->getKey())->toBe($first->getKey());
    \expect(Statement::query()->count())->toBe(1);
});

\test('updateDays persists cash, card, wolt, bolt, foodora and recomputes total', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $service = \app(StatementService::class);

    $statement = $service->findOrCreateForMonth($user, $store, 2026, 6);
    $firstDay = $statement->getDays()->first();
    \assert($firstDay instanceof StatementDay);

    $service->updateDays($statement, [
        [
            'date' => $firstDay->getDate(),
            'cash' => 10,
            'card' => 20,
            'wolt' => 30,
            'bolt' => 5,
            'foodora' => 5,
        ],
    ], $user);

    $firstDay->refresh();
    \expect($firstDay->getTotal())->toBe(70.0);
});

\test('clear zeroes every channel of every day', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $service = \app(StatementService::class);

    $statement = $service->findOrCreateForMonth($user, $store, 2026, 6);
    $firstDay = $statement->getDays()->first();
    \assert($firstDay instanceof StatementDay);

    $firstDay->update(['cash' => 100, 'total' => 100]);

    $service->clear($statement, $user);

    $firstDay->refresh();
    \expect($firstDay->getCash())->toBe(0.0);
    \expect($firstDay->getTotal())->toBe(0.0);
});

\test('calculateInvestment sums consumption and excludes transfers', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $warehouse = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => true,
    ]);
    $store = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);
    $item = Item::factory()->create([
        'user_id' => $user->getKey(),
        'purchase_price' => '5.00',
    ]);
    $consumption = StockMovement::factory()->consumption($store)->byUser($user)->create([
        'user_id' => $user->getKey(),
        'occurred_at' => '2026-06-15 10:00:00',
    ]);
    StockMovementItem::query()->create([
        'stock_movement_id' => $consumption->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 4,
        'quantity_difference' => -4,
        'classification' => StockMovementClassificationEnum::CONSUMPTION->value,
        'total' => 20.0,
    ]);
    $consumption->update(['reversed_at' => '2026-07-02 10:00:00']);

    $transfer = StockMovement::factory()->transfer($warehouse)->byUser($user)->create([
        'user_id' => $user->getKey(),
        'source_store_id' => $store->getKey(),
        'store_id' => $warehouse->getKey(),
        'occurred_at' => '2026-06-20 10:00:00',
    ]);
    StockMovementItem::query()->create([
        'stock_movement_id' => $transfer->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 2,
        'quantity_difference' => -2,
        'total' => 10.0,
    ]);

    $service = \app(StatementService::class);
    $statement = $service->findOrCreateForMonth($user, $store, 2026, 6);

    \expect($service->calculateInvestment($statement))->toBe(20.0);
});

\test('buildMetrics computes revenue, margin and channel breakdown', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $service = \app(StatementService::class);

    $statement = $service->findOrCreateForMonth($user, $store, 2026, 6);
    $firstDay = $statement->getDays()->first();
    \assert($firstDay instanceof StatementDay);
    $secondDay = $statement->getDays()->skip(1)->first();
    \assert($secondDay instanceof StatementDay);

    $firstDay->update(['cash' => 50, 'card' => 50, 'wolt' => 50, 'bolt' => 50, 'bolt_cash' => 10, 'foodora' => 50, 'total' => 260]);
    $secondDay->update(['cash' => 25, 'card' => 0, 'wolt' => 0, 'bolt' => 0, 'bolt_cash' => 0, 'foodora' => 0, 'total' => 25]);

    $metrics = $service->buildMetrics($statement, $statement->getDays(), 100.0);

    \expect($metrics['total_revenue'])->toBe(285.0);
    \expect($metrics['investment'])->toBe(100.0);
    \expect($metrics['card_provision'])->toBe(0.50);
    \expect($metrics['marketplace_provision'])->toBe(48.0);
    \expect($metrics['provisions'])->toBe(48.50);
    \expect($metrics['gross_margin'])->toBe(136.50);
    \expect($metrics['margin_percent'])->toBe(47.89);
    \expect($metrics['channels']['cash'])->toBe(75.0);
    \expect($metrics['channels']['wolt'])->toBe(50.0);
    \expect($metrics['channels']['bolt_cash'])->toBe(10.0);
    \expect($metrics['daily_average'])->toBe(142.5);
});

\test('buildMetrics deducts card provision at 1% and marketplace provision at 30%', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $service = \app(StatementService::class);

    $statement = $service->findOrCreateForMonth($user, $store, 2026, 6);
    $firstDay = $statement->getDays()->first();
    \assert($firstDay instanceof StatementDay);

    $firstDay->update(['cash' => 1000, 'card' => 1000, 'wolt' => 1000, 'bolt' => 1000, 'bolt_cash' => 1000, 'foodora' => 1000, 'total' => 6000]);

    $metrics = $service->buildMetrics($statement, $statement->getDays(), 0.0);

    \expect($metrics['card_provision'])->toBe(10.0);
    \expect($metrics['marketplace_provision'])->toBe(1200.0);
    \expect($metrics['provisions'])->toBe(1210.0);
    \expect($metrics['gross_margin'])->toBe(4790.0);
});

\test('buildMetrics leaves pure cash revenue free of provisions', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $service = \app(StatementService::class);

    $statement = $service->findOrCreateForMonth($user, $store, 2026, 6);
    $firstDay = $statement->getDays()->first();
    \assert($firstDay instanceof StatementDay);

    $firstDay->update(['cash' => 500, 'card' => 0, 'wolt' => 0, 'bolt' => 0, 'bolt_cash' => 0, 'foodora' => 0, 'total' => 500]);

    $metrics = $service->buildMetrics($statement, $statement->getDays(), 100.0);

    \expect($metrics['card_provision'])->toBe(0.0);
    \expect($metrics['marketplace_provision'])->toBe(0.0);
    \expect($metrics['provisions'])->toBe(0.0);
    \expect($metrics['gross_margin'])->toBe(400.0);
});

\test('buildReport aggregates across stores and time when no filters are given', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $storeA = Store::factory()->create(['user_id' => $user->getKey()]);
    $storeB = Store::factory()->create(['user_id' => $user->getKey()]);
    $service = \app(StatementService::class);

    $statementA = $service->findOrCreateForMonth($user, $storeA, 2026, 5);
    $statementB = $service->findOrCreateForMonth($user, $storeB, 2026, 6);

    $statementA->getDays()->first()?->update(['cash' => 100, 'total' => 100]);
    $statementB->getDays()->first()?->update(['card' => 200, 'total' => 200]);

    $report = $service->buildReport($user, null, null, null);

    \expect($report['totals']['total_revenue'])->toBe(300.0);
    \expect($report['channels']['cash'])->toBe(100.0);
    \expect($report['channels']['card'])->toBe(200.0);
    \expect($report['days_with_revenue'])->toBe(2);
});

\test('buildReport filters by store and month', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $service = \app(StatementService::class);

    $statement = $service->findOrCreateForMonth($user, $store, 2026, 6);
    $statement->getDays()->first()?->update(['wolt' => 500, 'total' => 500]);

    $report = $service->buildReport($user, $store->getKey(), 2026, 6);

    \expect($report['totals']['total_revenue'])->toBe(500.0);
    \expect($report['channels']['wolt'])->toBe(500.0);
    \expect($report['marketplace_provision'] ?? $report['totals']['marketplace_provision'])->toBe(150.0);
    \expect($report['totals']['marketplace_provision'])->toBe(150.0);
});

\test('buildReport excludes open inventory drafts from coverage', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    $draft = InventorySession::factory()->forStore($store)->byUser($user)->create([
        'status' => 'draft',
        'started_at' => '2026-06-10 10:00:00',
        'counted_at' => null,
    ]);
    InventorySessionItem::factory()->create([
        'session_id' => $draft->getKey(),
        'item_id' => $item->getKey(),
        'observation_started_at' => '2026-06-01 10:00:00',
    ]);

    $report = \app(StatementService::class)->buildReport($user, $store->getKey(), 2026, 6);

    \expect($report['inventory_coverage'])->toBe([
        'average_days' => 0.0,
        'covered_items' => 0,
        'last_inventory_at' => null,
    ]);
});

\test('buildReport exposes a daily series for the line chart', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $service = \app(StatementService::class);

    $statement = $service->findOrCreateForMonth($user, $store, 2026, 6);

    $report = $service->buildReport($user, $store->getKey(), 2026, 6);

    \expect($report['daily'])->toHaveCount(30);
    \expect($report['daily'][0])->toHaveKeys(['label', 'value']);
});
