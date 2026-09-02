<?php

declare(strict_types=1);

use App\Enums\AdjustmentReasonEnum;
use App\Enums\StockMovementTypeEnum;
use App\Models\InventorySession;
use App\Models\InventorySessionItem;
use App\Models\Item;
use App\Models\StockMovementItem;
use App\Models\StockMovementSequence;
use App\Models\Store;
use App\Models\StoreItem;
use App\Notifications\OperationalActivitySlackNotification;
use App\Services\StockMovementService;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Thinkycz\LaravelCore\Support\Config;

\test('transfer notifications reach both stores and deduplicate shared channels', function (): void {
    Notification::fake();
    Config::inject()->assign('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $warehouse->update(['slack_channel' => '#operations']);
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'name' => 'Brno Outlet',
        'slack_channel' => '#operations',
    ]);
    $item = Item::factory()->create(['user_id' => $user->getKey(), 'purchase_price' => '2.00']);
    StoreItem::factory()->create(['store_id' => $warehouse->getKey(), 'item_id' => $item->getKey(), 'quantity' => 10]);
    $service = \app(StockMovementService::class);
    $movement = $service->createMovement([
        'mode' => 'transfer',
        'source_store_id' => $warehouse->getKey(),
        'store_id' => $retail->getKey(),
        'items' => [['item_id' => $item->getKey(), 'quantity' => 3]],
    ], $user);

    Notification::assertSentOnDemandTimes(OperationalActivitySlackNotification::class, 1);
    $retail->update(['slack_channel' => '#brno']);
    $service->reverseMovement($movement, $user, 'Test reversal');

    Notification::assertSentOnDemandTimes(OperationalActivitySlackNotification::class, 3);
    Notification::assertSentOnDemand(
        OperationalActivitySlackNotification::class,
        static fn(OperationalActivitySlackNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routeNotificationFor('slack') === '#brno' && $notification->getStoreName() === 'Brno Outlet',
    );
});

\test('single-store manual movement notifies only its affected store', function (): void {
    Notification::fake();
    Config::inject()->assign('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $warehouse->update(['slack_channel' => '#warehouse']);
    $item = Item::factory()->create(['user_id' => $user->getKey(), 'purchase_price' => '3.50']);

    \app(StockMovementService::class)->createMovement([
        'mode' => 'incoming',
        'store_id' => $warehouse->getKey(),
        'items' => [['item_id' => $item->getKey(), 'quantity' => 4]],
    ], $user);

    Notification::assertSentOnDemandTimes(OperationalActivitySlackNotification::class, 1);
});

\test('incoming movement adds stock to the destination store and assigns the next number', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create([
        'user_id' => $user->getKey(),
        'purchase_price' => '3.50',
    ]);

    $movement = \app(StockMovementService::class)->createMovement([
        'mode' => 'incoming',
        'store_id' => $warehouse->getKey(),
        'note' => 'Pondělní příjem',
        'items' => [[
            'item_id' => $item->getKey(),
            'quantity' => 4,
        ]],
    ], $user);

    \expect($movement->getType())->toBe(StockMovementTypeEnum::INCOMING);
    \expect($movement->getSourceStoreId())->toBeNull();
    \expect($movement->getNumber())->toStartWith('IN-');
    \expect($movement->getTotalQuantity())->toBe(4);
    \expect((float) $movement->getTotalValue())->toBe(14.0);
    \expect((int) StoreItem::query()
        ->where('store_id', $warehouse->getKey())
        ->where('item_id', $item->getKey())
        ->value('quantity'))->toBe(4);

    $row = StockMovementItem::query()->where('stock_movement_id', $movement->getKey())->first();
    \expect($row)->not->toBeNull();
    \expect($row->getQuantityBefore())->toBe(0);
    \expect($row->getQuantityAfter())->toBe(4);
    \expect($row->getQuantityDifference())->toBe(4);
    \expect($row->getAdjustmentReason())->toBeNull();

    \expect(StockMovementSequence::query()->count())->toBe(1);
});

\test('transfer movement moves stock between two stores', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'name' => 'Brno Outlet',
    ]);
    $item = Item::factory()->create([
        'user_id' => $user->getKey(),
        'purchase_price' => '2.00',
    ]);
    StoreItem::query()->create([
        'store_id' => $warehouse->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 10,
    ]);

    $movement = \app(StockMovementService::class)->createMovement([
        'mode' => 'transfer',
        'source_store_id' => $warehouse->getKey(),
        'store_id' => $retail->getKey(),
        'items' => [[
            'item_id' => $item->getKey(),
            'quantity' => 3,
        ]],
    ], $user);

    \expect($movement->getType())->toBe(StockMovementTypeEnum::TRANSFER);
    \expect($movement->getNumber())->toStartWith('TR-');

    \expect((int) StoreItem::query()
        ->where('store_id', $warehouse->getKey())
        ->where('item_id', $item->getKey())
        ->value('quantity'))->toBe(7);
    \expect((int) StoreItem::query()
        ->where('store_id', $retail->getKey())
        ->where('item_id', $item->getKey())
        ->value('quantity'))->toBe(3);
});

\test('manual consumption deducts one store and creates a classified ledger row', function (): void {
    [$user, $store] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey(), 'purchase_price' => '5.00']);
    StoreItem::factory()->create(['store_id' => $store->getKey(), 'item_id' => $item->getKey(), 'quantity' => 10]);

    $movement = \app(StockMovementService::class)->createMovement([
        'mode' => 'consumption',
        'store_id' => $store->getKey(),
        'items' => [['item_id' => $item->getKey(), 'quantity' => 3]],
    ], $user);

    $row = StockMovementItem::query()->where('stock_movement_id', $movement->getKey())->firstOrFail();
    \expect($movement->getType())->toBe(StockMovementTypeEnum::CONSUMPTION);
    \expect($movement->getSourceStoreId())->toBeNull();
    \expect($movement->getNumber())->toStartWith('CON-');
    \expect($row->getClassification()?->value)->toBe('consumption');
    \expect($row->getQuantityDifference())->toBe(-3);
    \expect((float) StoreItem::query()->where('store_id', $store->getKey())->where('item_id', $item->getKey())->value('quantity'))->toBe(7.0);
});

\test('outgoing movement fails when source has insufficient stock', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    StoreItem::query()->create([
        'store_id' => $warehouse->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 1,
    ]);

    \app(StockMovementService::class)->createMovement([
        'mode' => 'transfer',
        'source_store_id' => $warehouse->getKey(),
        'store_id' => $retail->getKey(),
        'items' => [[
            'item_id' => $item->getKey(),
            'quantity' => 5,
        ]],
    ], $user);
})->throws(ValidationException::class);

\test('adjustment movement records before/after quantities and reason', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    StoreItem::query()->create([
        'store_id' => $warehouse->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 6,
    ]);

    $movement = \app(StockMovementService::class)->createMovement([
        'mode' => 'adjustment',
        'store_id' => $warehouse->getKey(),
        'items' => [[
            'item_id' => $item->getKey(),
            'quantity_after' => 4,
            'adjustment_reason' => AdjustmentReasonEnum::DAMAGED->value,
        ]],
    ], $user);

    \expect($movement->getType())->toBe(StockMovementTypeEnum::ADJUSTMENT);
    \expect((int) StoreItem::query()
        ->where('store_id', $warehouse->getKey())
        ->where('item_id', $item->getKey())
        ->value('quantity'))->toBe(4);

    $row = StockMovementItem::query()->where('stock_movement_id', $movement->getKey())->first();
    \expect($row->getQuantityBefore())->toBe(6);
    \expect($row->getQuantityAfter())->toBe(4);
    \expect($row->getQuantityDifference())->toBe(-2);
    \expect($row->getAdjustmentReason())->toBe(AdjustmentReasonEnum::DAMAGED);
});

\test('adjustment rejects a reason whose sign does not match the change', function (): void {
    [$user, $store] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    StoreItem::factory()->create(['store_id' => $store->getKey(), 'item_id' => $item->getKey(), 'quantity' => 5]);

    \app(StockMovementService::class)->createMovement([
        'mode' => 'adjustment',
        'store_id' => $store->getKey(),
        'items' => [[
            'item_id' => $item->getKey(),
            'quantity_after' => 7,
            'adjustment_reason' => AdjustmentReasonEnum::DAMAGED->value,
        ]],
    ], $user);
})->throws(ValidationException::class);

\test('creating a movement with an unknown item fails validation', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();

    \app(StockMovementService::class)->createMovement([
        'mode' => 'incoming',
        'store_id' => $warehouse->getKey(),
        'items' => [[
            'item_id' => 99999,
            'quantity' => 1,
        ]],
    ], $user);
})->throws(ValidationException::class);

\test('subsequent movements of the same type increment the sequence', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    $first = \app(StockMovementService::class)->createMovement([
        'mode' => 'incoming',
        'store_id' => $warehouse->getKey(),
        'items' => [['item_id' => $item->getKey(), 'quantity' => 1]],
    ], $user);

    $second = \app(StockMovementService::class)->createMovement([
        'mode' => 'incoming',
        'store_id' => $warehouse->getKey(),
        'items' => [['item_id' => $item->getKey(), 'quantity' => 1]],
    ], $user);

    \expect($first->getNumber())->not->toBe($second->getNumber());
    \expect((int) StockMovementSequence::query()->value('last_number'))->toBe(2);
});

\test('decimal quantities remain exact across receipt transfer consumption and reversal', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey(), 'purchase_price' => 10]);
    $service = \app(StockMovementService::class);

    $service->createMovement([
        'mode' => 'incoming', 'store_id' => $warehouse->getKey(),
        'items' => [['item_id' => $item->getKey(), 'quantity' => '1.250']],
    ], $user);
    $service->createMovement([
        'mode' => 'transfer', 'source_store_id' => $warehouse->getKey(), 'store_id' => $store->getKey(),
        'items' => [['item_id' => $item->getKey(), 'quantity' => '0.250']],
    ], $user);
    $consumption = $service->createMovement([
        'mode' => 'consumption', 'store_id' => $store->getKey(),
        'items' => [['item_id' => $item->getKey(), 'quantity' => '0.001']],
    ], $user);

    \expect((float) StoreItem::query()->where('store_id', $warehouse->getKey())->where('item_id', $item->getKey())->value('quantity'))->toBe(1.0)
        ->and((float) StoreItem::query()->where('store_id', $store->getKey())->where('item_id', $item->getKey())->value('quantity'))->toBe(0.249);

    $service->reverseMovement($consumption, $user, 'Decimal regression');
    \expect((float) StoreItem::query()->where('store_id', $store->getKey())->where('item_id', $item->getKey())->value('quantity'))->toBe(0.25);
});

\test('admin backdating is allowed only after the latest closed inventory', function (): void {
    [$user, $store] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    $countedAt = Carbon::parse('2026-07-10 12:00:00');
    $session = InventorySession::factory()->create([
        'user_id' => $user->getKey(),
        'store_id' => $store->getKey(),
        'status' => 'closed',
        'started_at' => $countedAt,
        'counted_at' => $countedAt,
        'closed_at' => $countedAt,
    ]);
    InventorySessionItem::factory()->create([
        'session_id' => $session->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => '1.000',
        'counted_at' => $countedAt,
    ]);

    $service = \app(StockMovementService::class);
    $payload = [
        'mode' => 'incoming',
        'store_id' => $store->getKey(),
        'items' => [['item_id' => $item->getKey(), 'quantity' => '1.000']],
    ];

    try {
        $service->createMovement([...$payload, 'occurred_at' => '2026-07-10 11:59:59'], $user);
        \expect(false)->toBeTrue('Backdating before a closed count must fail.');
    } catch (ValidationException $exception) {
        \expect($exception->errors())->toHaveKey('occurred_at');
    }

    $movement = $service->createMovement([...$payload, 'occurred_at' => '2026-07-10 12:00:01'], $user);
    \expect($movement->getOccurredAt()->toDateTimeString())->toBe('2026-07-10 12:00:01');
});
