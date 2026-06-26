<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\Statement;
use App\Models\StockMovement;
use App\Models\StockMovementItem;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('guest is redirected from dashboard to login', function (): void {
    $response = $this->get('/dashboard');

    $response->assertRedirect('/login');
});

\test('authenticated user can view dashboard with metrics', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $response = $this->be($user, 'users')->get('/dashboard', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'Dashboard');
    $response->assertJsonPath('props.auth.user.email', $user->getEmail());
    $response->assertJsonStructure([
        'props' => [
            'active_store',
            'metrics' => [
                'inventory_value',
                'items_count',
                'low_stock_items',
                'today_movements',
                'month_incoming',
                'month_outgoing',
            ],
            'stock_status' => ['in_stock', 'low_stock', 'out_of_stock'],
            'top_consumed',
            'recent_movements',
            'recent_statements',
        ],
    ]);
});

\test('dashboard returns empty payload without an active store', function (): void {
    $user = UserFactory::new()->admin()->createOne();

    $response = $this->be($user, 'users')->get('/dashboard', $this->inertiaHeaders());

    $response->assertOk();
    \expect($response->json('props.active_store'))->toBeNull();
    \expect((float) $response->json('props.metrics.inventory_value'))->toBe(0.0);
    \expect($response->json('props.metrics.items_count'))->toBe(0);
    \expect($response->json('props.stock_status.in_stock'))->toBe(0);
    \expect($response->json('props.top_consumed'))->toBe([]);
    \expect($response->json('props.recent_movements'))->toBe([]);
    \expect($response->json('props.recent_statements'))->toBe([]);
});

\test('dashboard scopes inventory value to the active store', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $other = Store::factory()->create(['user_id' => $user->getKey()]);

    $itemLocal = Item::factory()->create(['user_id' => $user->getKey(), 'purchase_price' => '10.00']);
    $itemOther = Item::factory()->create(['user_id' => $user->getKey(), 'purchase_price' => '99.00']);

    StoreItem::factory()->create(['store_id' => $warehouse->getKey(), 'item_id' => $itemLocal->getKey(), 'quantity' => 5]);
    StoreItem::factory()->create(['store_id' => $other->getKey(), 'item_id' => $itemOther->getKey(), 'quantity' => 100]);

    $response = $this->be($user, 'users')
        ->get('/dashboard?store_id=' . $warehouse->getKey(), $this->inertiaHeaders());

    \expect((float) $response->json('props.metrics.inventory_value'))->toBe(50.0);
    \expect($response->json('props.active_store.id'))->toBe($warehouse->getKey());
});

\test('dashboard classifies stock status for the active store', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();

    $itemInStock = Item::factory()->create(['user_id' => $user->getKey()]);
    $itemLowStock = Item::factory()->create(['user_id' => $user->getKey()]);
    $itemOutOfStock = Item::factory()->create(['user_id' => $user->getKey()]);

    StoreItem::factory()->create(['store_id' => $warehouse->getKey(), 'item_id' => $itemInStock->getKey(), 'quantity' => 20]);
    StoreItem::factory()->create(['store_id' => $warehouse->getKey(), 'item_id' => $itemLowStock->getKey(), 'quantity' => 3]);
    StoreItem::factory()->create(['store_id' => $warehouse->getKey(), 'item_id' => $itemOutOfStock->getKey(), 'quantity' => 0]);

    $response = $this->be($user, 'users')
        ->get('/dashboard?store_id=' . $warehouse->getKey(), $this->inertiaHeaders());

    \expect($response->json('props.stock_status.in_stock'))->toBe(1);
    \expect($response->json('props.stock_status.low_stock'))->toBe(1);
    \expect($response->json('props.stock_status.out_of_stock'))->toBe(1);
    \expect($response->json('props.metrics.items_count'))->toBe(3);
    \expect($response->json('props.metrics.low_stock_items'))->toBe(1);
});

\test('dashboard scopes recent movements to the active store', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $other = Store::factory()->create(['user_id' => $user->getKey()]);

    $local = StockMovement::factory()
        ->incoming()
        ->byUser($user)
        ->create(['user_id' => $user->getKey(), 'store_id' => $warehouse->getKey()]);
    $foreign = StockMovement::factory()
        ->incoming()
        ->byUser($user)
        ->create(['user_id' => $user->getKey(), 'store_id' => $other->getKey()]);

    $response = $this->be($user, 'users')
        ->get('/dashboard?store_id=' . $warehouse->getKey(), $this->inertiaHeaders());

    $ids = \array_column($response->json('props.recent_movements'), 'id');
    \expect($ids)->toContain($local->getKey());
    \expect($ids)->not->toContain($foreign->getKey());
});

\test('dashboard aggregates top consumed items for the active store', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $other = Store::factory()->create(['user_id' => $user->getKey()]);

    $itemLocal = Item::factory()->create(['user_id' => $user->getKey(), 'title' => 'Local top']);
    $itemOther = Item::factory()->create(['user_id' => $user->getKey(), 'title' => 'Other-store top']);

    $localMovement = StockMovement::factory()
        ->outgoing($warehouse)
        ->byUser($user)
        ->create(['user_id' => $user->getKey(), 'source_store_id' => $warehouse->getKey()]);
    StockMovementItem::factory()->create([
        'stock_movement_id' => $localMovement->getKey(),
        'item_id' => $itemLocal->getKey(),
        'quantity_difference' => 4,
        'total' => 40.0,
    ]);

    $otherMovement = StockMovement::factory()
        ->outgoing($other)
        ->byUser($user)
        ->create(['user_id' => $user->getKey(), 'source_store_id' => $other->getKey()]);
    StockMovementItem::factory()->create([
        'stock_movement_id' => $otherMovement->getKey(),
        'item_id' => $itemOther->getKey(),
        'quantity_difference' => 99,
        'total' => 9999.0,
    ]);

    $response = $this->be($user, 'users')
        ->get('/dashboard?store_id=' . $warehouse->getKey(), $this->inertiaHeaders());

    $topConsumed = $response->json('props.top_consumed');
    \expect($topConsumed)->toHaveCount(1);
    \expect($topConsumed[0]['item_id'])->toBe($itemLocal->getKey());
    \expect((float) $topConsumed[0]['total_quantity'])->toBe(4.0);
});

\test('dashboard scopes recent statements to the active store', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $other = Store::factory()->create(['user_id' => $user->getKey()]);

    $localStatement = Statement::factory()->create([
        'user_id' => $user->getKey(),
        'store_id' => $warehouse->getKey(),
        'year' => 2026,
        'month' => 5,
    ]);
    Statement::factory()->create([
        'user_id' => $user->getKey(),
        'store_id' => $other->getKey(),
        'year' => 2026,
        'month' => 5,
    ]);

    $response = $this->be($user, 'users')
        ->get('/dashboard?store_id=' . $warehouse->getKey(), $this->inertiaHeaders());

    $ids = \array_column($response->json('props.recent_statements'), 'id');
    \expect($ids)->toContain($localStatement->getKey());
    \expect(\count($ids))->toBe(1);
});
