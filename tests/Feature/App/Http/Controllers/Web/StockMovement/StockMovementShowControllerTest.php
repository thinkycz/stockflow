<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreItem;
use App\Services\InventorySessionService;

\test('stock movement show page is reachable', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $movement = StockMovement::factory()->incoming()->byUser($user)->create(['user_id' => $user->getKey()]);

    $response = $this->be($user, 'users')->get("/stock-movements/{$movement->getKey()}", $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'stock-movements/Show');
    $response->assertJsonPath('props.movement.id', $movement->getKey());
});

\test('stock movement show labels a warehouse dispatch as outgoing', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);
    $movement = StockMovement::factory()->transfer($retail)->create([
        'user_id' => $user->getKey(),
        'source_store_id' => $warehouse->getKey(),
    ]);

    $response = $this->be($user, 'users')->get("/stock-movements/{$movement->getKey()}", $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('props.movement.display_label_key', 'outgoing');
});

\test('stock movement show 404s for another user', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    [$other] = \createIsolatedUserWithWarehouse();
    $otherMovement = StockMovement::factory()->incoming()->byUser($other)->create(['user_id' => $other->getKey()]);

    $this->be($user, 'users')->get("/stock-movements/{$otherMovement->getKey()}")->assertNotFound();
});

\test('inventory reconciliation detail exposes signed increases and decreases', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $decreased = Item::factory()->create([
        'user_id' => $user->getKey(),
        'purchase_price' => 10,
    ]);
    $increased = Item::factory()->create([
        'user_id' => $user->getKey(),
        'purchase_price' => 10,
    ]);
    foreach ([$decreased, $increased] as $item) {
        StoreItem::query()->create([
            'store_id' => $store->getKey(),
            'item_id' => $item->getKey(),
            'quantity' => 10,
        ]);
    }

    $session = \app(InventorySessionService::class)->createSession($user, $store, [
        ['item_id' => $decreased->getKey(), 'quantity' => 7],
        ['item_id' => $increased->getKey(), 'quantity' => 12],
    ]);
    $movement = StockMovement::query()->where('inventory_session_id', $session->getKey())->firstOrFail();

    $response = $this->be($user, 'users')->get(
        "/stock-movements/{$movement->getKey()}",
        $this->inertiaHeaders(),
    );

    $response->assertOk();
    $response->assertJsonPath('props.movement.type', 'inventory_reconciliation');
    $response->assertJsonPath('props.rows.0.quantity_difference', -3);
    $response->assertJsonPath('props.rows.0.classification', 'consumption');
    $response->assertJsonPath('props.rows.0.signed_total', -30);
    $response->assertJsonPath('props.rows.1.quantity_difference', 2);
    $response->assertJsonPath('props.rows.1.classification', 'inventory_correction');
    $response->assertJsonPath('props.rows.1.signed_total', 20);
    $response->assertJsonPath('props.movement.net_value', -10);
});
