<?php

declare(strict_types=1);

use App\Enums\AdjustmentReasonEnum;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockMovementItem;
use App\Models\Store;
use App\Models\StoreItem;
use Database\Factories\UserFactory;

\test('guest is redirected from reports to login', function (): void {
    $this->get('/reports')->assertRedirect('/login');
});

\test('authenticated user can view reports', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();

    $response = $this->be($user, 'users')->get('/reports', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'reports/Index');
    $response->assertJsonStructure([
        'props' => [
            'active_store',
            'inventory_value',
            'monthly' => ['incoming', 'outgoing'],
            'most_moved',
            'adjustments',
            'reasons',
            'statement_report',
            'statement_filter',
        ],
    ]);
});

\test('reports only show own data', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    [$other] = \createIsolatedUserWithWarehouse();
    StockMovement::factory()->incoming()->byUser($other)->create(['user_id' => $other->getKey()]);

    $response = $this->be($user, 'users')->get('/reports', $this->inertiaHeaders());

    \expect((float) $response->json('props.inventory_value'))->toBe(0.0);
});

\test('reports scope inventory value to the active store', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $other = Store::factory()->create(['user_id' => $user->getKey()]);

    $itemA = Item::factory()->create(['user_id' => $user->getKey(), 'purchase_price' => '10.00']);
    $itemB = Item::factory()->create(['user_id' => $user->getKey(), 'purchase_price' => '20.00']);

    StoreItem::factory()->create(['store_id' => $warehouse->getKey(), 'item_id' => $itemA->getKey(), 'quantity' => 4]);
    StoreItem::factory()->create(['store_id' => $other->getKey(), 'item_id' => $itemB->getKey(), 'quantity' => 3]);

    $response = $this->be($user, 'users')
        ->get('/reports?store_id=' . $warehouse->getKey(), $this->inertiaHeaders());

    \expect((float) $response->json('props.inventory_value'))->toBe(40.0);
    \expect($response->json('props.active_store.id'))->toBe($warehouse->getKey());
});

\test('reports scope monthly incoming and outgoing to the active store', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $other = Store::factory()->create(['user_id' => $user->getKey()]);

    $item = Item::factory()->create(['user_id' => $user->getKey(), 'purchase_price' => '5.00']);
    StoreItem::factory()->create(['store_id' => $warehouse->getKey(), 'item_id' => $item->getKey(), 'quantity' => 50]);
    StoreItem::factory()->create(['store_id' => $other->getKey(), 'item_id' => $item->getKey(), 'quantity' => 50]);

    StockMovement::factory()
        ->incoming()
        ->byUser($user)
        ->create([
            'user_id' => $user->getKey(),
            'store_id' => $warehouse->getKey(),
            'total_value' => 100.0,
            'created_at' => \now(),
        ]);

    StockMovement::factory()
        ->incoming()
        ->byUser($user)
        ->create([
            'user_id' => $user->getKey(),
            'store_id' => $other->getKey(),
            'total_value' => 999.0,
            'created_at' => \now(),
        ]);

    StockMovement::factory()
        ->outgoing($warehouse)
        ->byUser($user)
        ->create([
            'user_id' => $user->getKey(),
            'source_store_id' => $warehouse->getKey(),
            'total_value' => 30.0,
            'created_at' => \now(),
        ]);

    StockMovement::factory()
        ->outgoing($other)
        ->byUser($user)
        ->create([
            'user_id' => $user->getKey(),
            'source_store_id' => $other->getKey(),
            'total_value' => 999.0,
            'created_at' => \now(),
        ]);

    $response = $this->be($user, 'users')
        ->get('/reports?store_id=' . $warehouse->getKey(), $this->inertiaHeaders());

    \expect((float) $response->json('props.monthly.incoming'))->toBe(100.0);
    \expect((float) $response->json('props.monthly.outgoing'))->toBe(30.0);
});

\test('reports scope most moved items to the active store', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $other = Store::factory()->create(['user_id' => $user->getKey()]);

    $itemLocal = Item::factory()->create(['user_id' => $user->getKey(), 'title' => 'Local favorite']);
    $itemOther = Item::factory()->create(['user_id' => $user->getKey(), 'title' => 'Other-store item']);

    $localMovement = StockMovement::factory()
        ->incoming()
        ->byUser($user)
        ->create(['user_id' => $user->getKey(), 'store_id' => $warehouse->getKey()]);
    StockMovementItem::factory()->create([
        'stock_movement_id' => $localMovement->getKey(),
        'item_id' => $itemLocal->getKey(),
        'quantity_difference' => 8,
        'total' => 80.0,
    ]);

    $otherMovement = StockMovement::factory()
        ->incoming()
        ->byUser($user)
        ->create(['user_id' => $user->getKey(), 'store_id' => $other->getKey()]);
    StockMovementItem::factory()->create([
        'stock_movement_id' => $otherMovement->getKey(),
        'item_id' => $itemOther->getKey(),
        'quantity_difference' => 999,
        'total' => 9999.0,
    ]);

    $response = $this->be($user, 'users')
        ->get('/reports?store_id=' . $warehouse->getKey(), $this->inertiaHeaders());

    $mostMoved = $response->json('props.most_moved');
    \expect($mostMoved)->toHaveCount(1);
    \expect($mostMoved[0]['item_id'])->toBe($itemLocal->getKey());
    \expect((float) $mostMoved[0]['total_quantity'])->toBe(8.0);
    \expect((float) $mostMoved[0]['total_value'])->toBe(80.0);
});

\test('reports scope adjustments to the active store', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $other = Store::factory()->create(['user_id' => $user->getKey()]);

    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    $localMovement = StockMovement::factory()
        ->incoming()
        ->byUser($user)
        ->create(['user_id' => $user->getKey(), 'store_id' => $warehouse->getKey()]);
    StockMovementItem::factory()->create([
        'stock_movement_id' => $localMovement->getKey(),
        'item_id' => $item->getKey(),
        'quantity_difference' => 2,
        'adjustment_reason' => AdjustmentReasonEnum::DAMAGED,
    ]);

    $otherMovement = StockMovement::factory()
        ->incoming()
        ->byUser($user)
        ->create(['user_id' => $user->getKey(), 'store_id' => $other->getKey()]);
    StockMovementItem::factory()->create([
        'stock_movement_id' => $otherMovement->getKey(),
        'item_id' => $item->getKey(),
        'quantity_difference' => 99,
        'adjustment_reason' => AdjustmentReasonEnum::DAMAGED,
    ]);

    $response = $this->be($user, 'users')
        ->get('/reports?store_id=' . $warehouse->getKey(), $this->inertiaHeaders());

    $adjustments = $response->json('props.adjustments');
    \expect($adjustments)->toHaveCount(1);
    \expect($adjustments[0]['reason'])->toBe(AdjustmentReasonEnum::DAMAGED->value);
    \expect($adjustments[0]['rows_count'])->toBe(1);
    \expect($adjustments[0]['total_quantity'])->toBe(2);
});

\test('reports returns empty payload without an active store', function (): void {
    // Admin without any warehouses/retail stores — ActiveStoreResolver
    // returns null and the page should render with an empty payload.
    $user = UserFactory::new()->admin()->createOne();

    $response = $this->be($user, 'users')->get('/reports', $this->inertiaHeaders());

    $response->assertOk();
    \expect($response->json('props.active_store'))->toBeNull();
    \expect((float) $response->json('props.inventory_value'))->toBe(0.0);
    \expect($response->json('props.most_moved'))->toBe([]);
    \expect($response->json('props.adjustments'))->toBe([]);
    \expect((float) $response->json('props.monthly.incoming'))->toBe(0.0);
    \expect((float) $response->json('props.monthly.outgoing'))->toBe(0.0);
});
