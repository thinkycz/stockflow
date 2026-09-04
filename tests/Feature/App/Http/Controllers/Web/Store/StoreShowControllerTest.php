<?php

declare(strict_types=1);

use App\Models\InventorySession;
use App\Models\InventorySessionItem;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockMovementItem;
use App\Models\Store;
use App\Models\StoreItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

\test('store show page is reachable', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $user->getKey(),
        'slack_channel' => '#praha-provoz',
    ]);

    $response = $this->be($user, 'users')->get("/stores/{$store->getKey()}", $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'stores/Show');
    $response->assertJsonPath('props.store.id', $store->getKey());
    $response->assertJsonPath('props.store.slack_channel', '#praha-provoz');
});

\test('store show exposes the outgoing display label for warehouse dispatches', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);
    StockMovement::factory()->transfer($retail)->create([
        'user_id' => $user->getKey(),
        'source_store_id' => $warehouse->getKey(),
    ]);

    $response = $this->be($user, 'users')->get("/stores/{$warehouse->getKey()}", $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('props.movements.0.display_label_key', 'outgoing');
});

\test('store show 404s for another user', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    [$other] = \createIsolatedUserWithWarehouse();
    $otherStore = Store::factory()->create(['user_id' => $other->getKey()]);

    $this->be($user, 'users')->get("/stores/{$otherStore->getKey()}")->assertNotFound();
});

\test('store show inventory exposes status, sparkline and last count per item', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey(), 'purchase_price' => 12.5]);
    StoreItem::factory()->create([
        'store_id' => $store->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 0,
    ]);

    $response = $this->be($user, 'users')->get("/stores/{$store->getKey()}", $this->inertiaHeaders());

    $response->assertOk();
    $inventory = $response->json('props.inventory');
    \expect($inventory)->toHaveCount(1);
    $row = $inventory[0];
    \expect($row)->toHaveKey('item_id', $item->getKey());
    \expect($row)->toHaveKey('quantity', 0);
    \expect($row)->toHaveKey('total_value', 0.0);
    \expect($row)->toHaveKey('status', 'out');
    \expect($row)->toHaveKey('sparkline');
    \expect($row['sparkline'])->toBeArray();
    \expect($row)->toHaveKey('last_count_at', null);
    \expect($response->json('props.now'))->toBeString();
});

\test('store show exposes the latest closed inventory timestamp per item', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    StoreItem::factory()->create([
        'store_id' => $store->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 5,
    ]);
    $countedAt = Carbon::parse('2026-07-19 12:34:56');
    $session = InventorySession::factory()->forStore($store)->byUser($user)->create([
        'status' => 'closed',
        'counted_at' => $countedAt,
    ]);
    InventorySessionItem::factory()->create([
        'session_id' => $session->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 5,
        'counted_at' => $countedAt,
    ]);

    $response = $this->be($user, 'users')->get("/stores/{$store->getKey()}", $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('props.inventory.0.last_count_at', $countedAt->toJSON());
});

\test('store show reports no_data without sufficient inventory coverage', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    StoreItem::factory()->create([
        'store_id' => $store->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 5,
    ]);

    $response = $this->be($user, 'users')->get("/stores/{$store->getKey()}", $this->inertiaHeaders());

    $response->assertOk();
    $row = $response->json('props.inventory.0');
    \expect($row['status'])->toBe('no_data');
    \expect($row['days_until_stockout'])->toBeNull();
    \expect($row['last_count_at'])->toBeNull();
});

\test('store show now prop is a current timestamp', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $before = Carbon::now()->subSecond();

    $response = $this->be($user, 'users')->get("/stores/{$store->getKey()}", $this->inertiaHeaders());

    $response->assertOk();
    $now = Carbon::parse($response->json('props.now'));
    \expect($now->greaterThanOrEqualTo($before))->toBeTrue();
    \expect($now->lessThanOrEqualTo(Carbon::now()->addSecond()))->toBeTrue();
});

\test('store history is bounded while outgoing totals remain store specific and independent of page', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create(['user_id' => $user->getKey()]);
    StockMovement::factory()->transfer($retail)->count(55)->create([
        'user_id' => $user->getKey(), 'source_store_id' => $warehouse->getKey(), 'total_value' => '10.00',
    ]);
    $first = $this->be($user, 'users')->get('/stores/' . $warehouse->getKey(), $this->inertiaHeaders());
    $first->assertOk()->assertJsonCount(50, 'props.movements')
        ->assertJsonPath('props.movements_pagination.total', 55)
        ->assertJsonPath('props.metrics.total_transfer_out_movements', 55);
    $second = $this->get('/stores/' . $warehouse->getKey() . '?page=2', $this->inertiaHeaders());
    $second->assertOk()->assertJsonCount(5, 'props.movements')
        ->assertJsonPath('props.metrics', $first->json('props.metrics'));
    $this->get('/stores/' . $retail->getKey(), $this->inertiaHeaders())->assertOk()
        ->assertJsonPath('props.metrics.total_transfer_out_movements', 0);
});

\test('received item aggregates preserve fractional gross history across reversals and pages', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $other = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    foreach ([['0.125', '1.25', false], ['0.375', '3.75', true]] as [$quantity, $total, $reversed]) {
        $movement = StockMovement::factory()->incoming()->create([
            'user_id' => $user->getKey(), 'store_id' => $store->getKey(), 'total_value' => $total,
            'reversed_at' => $reversed ? \now() : null,
        ]);
        StockMovementItem::factory()->create(['stock_movement_id' => $movement->getKey(), 'item_id' => $item->getKey(), 'quantity' => $quantity, 'total' => $total]);
        if ($reversed) {
            $reversal = StockMovement::factory()->create([
                'user_id' => $user->getKey(), 'store_id' => $store->getKey(), 'type' => 'reversal', 'total_value' => '-3.75',
                'reversal_of_id' => $movement->getKey(),
            ]);
            StockMovementItem::factory()->create(['stock_movement_id' => $reversal->getKey(), 'item_id' => $item->getKey(), 'quantity' => '-0.375', 'total' => '-3.75']);
        }
    }
    $foreignMovement = StockMovement::factory()->incoming()->create(['user_id' => $user->getKey(), 'store_id' => $other->getKey()]);
    StockMovementItem::factory()->create(['stock_movement_id' => $foreignMovement->getKey(), 'item_id' => $item->getKey(), 'quantity' => '99.999', 'total' => '999.99']);
    StockMovement::factory()->transfer($store)->count(55)->create(['user_id' => $user->getKey(), 'source_store_id' => $warehouse->getKey()]);
    $first = $this->be($user, 'users')->get('/stores/' . $store->getKey(), $this->inertiaHeaders());
    $first->assertOk()->assertJsonCount(50, 'props.movements')->assertJsonCount(1, 'props.items_received')
        ->assertJsonPath('props.items_received.0.item_id', $item->getKey())
        ->assertJsonPath('props.items_received.0.movements_count', 2)
        ->assertJsonPath('props.items_received.0.total_quantity', 0.5)
        ->assertJsonPath('props.items_received.0.total_value', fn(float|int $value) => $value === 5 || $value === 5.0)
        ->assertJsonPath('props.metrics.total_received_value', fn(float|int $value) => $value === 5 || $value === 5.0);
    $this->get('/stores/' . $store->getKey() . '?page=2', $this->inertiaHeaders())->assertOk()
        ->assertJsonCount(8, 'props.movements')
        ->assertJsonPath('props.items_received', $first->json('props.items_received'))
        ->assertJsonPath('props.metrics', $first->json('props.metrics'));
});

\test('store history query volume and movement payload stay bounded at one thousand records', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->createOne(['user_id' => $user->getKey()]);
    $factory = StockMovement::factory()->transfer($retail)->state([
        'user_id' => $user->getKey(), 'source_store_id' => $warehouse->getKey(),
        'note' => null, 'total_value' => '10.00',
    ]);
    $factory->count(50)->create();
    $measure = function () use ($user, $warehouse): array {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $response = $this->be($user, 'users')->get('/stores/' . $warehouse->getKey(), $this->inertiaHeaders());
            $response->assertOk()->assertJsonCount(50, 'props.movements');

            return [\count(DB::getQueryLog()), \mb_strlen(\json_encode($response->json('props.movements'), \JSON_THROW_ON_ERROR))];
        } finally {
            DB::disableQueryLog();
        }
    };
    [$smallQueries, $smallBytes] = $measure();
    $factory->count(950)->create();
    [$largeQueries, $largeBytes] = $measure();
    \expect($largeQueries)->toBeLessThanOrEqual($smallQueries)
        ->and($largeBytes)->toBeLessThanOrEqual($smallBytes * 1.1);
});
