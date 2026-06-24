<?php

declare(strict_types=1);

use App\Models\InventorySession;
use App\Models\InventorySessionItem;
use App\Models\Item;
use App\Models\Store;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Support\Typer;

\test('admin sees the inventory-count history for the selected store', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $item = Item::factory()->create(['user_id' => $admin->getKey()]);

    $session = InventorySession::factory()
        ->forStore($store)
        ->byUser($admin)
        ->create([
            'counted_at' => Carbon::now()->subDays(2),
        ]);
    InventorySessionItem::factory()->create([
        'session_id' => $session->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 7,
    ]);

    $response = $this->actingAs($admin)->get(\route('inventory-counts.history', [
        'store_id' => $store->getKey(),
    ]));

    $response->assertOk();
    $response->assertInertia(static fn($page) => $page
        ->component('inventory-counts/History')
        ->where('store.id', $store->getKey())
        ->where('store.name', $store->getName())
        ->has('rows', 1)
        ->where('rows.0.id', $session->getKey())
        ->where('rows.0.item_count', 1)
        ->where('is_admin', true));
});

\test('limited user is pinned to their assigned store', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $storeA = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $storeB = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($storeA)->createOne(), User::class);

    // Inventory sessions are owned by the admin (parent) so the limited user
    // can browse them through the parent-scope lookup.
    $sessionA = InventorySession::factory()
        ->forStore($storeA)
        ->byUser($admin)
        ->create();
    InventorySessionItem::factory()->create([
        'session_id' => $sessionA->getKey(),
        'quantity' => 3,
    ]);
    $sessionB = InventorySession::factory()
        ->forStore($storeB)
        ->byUser($admin)
        ->create();
    InventorySessionItem::factory()->create([
        'session_id' => $sessionB->getKey(),
        'quantity' => 11,
    ]);

    // Requesting store B as the limited user must be refused.
    $this->actingAs($limited)
        ->get(\route('inventory-counts.history', ['store_id' => $storeB->getKey()]))
        ->assertForbidden();

    $response = $this->actingAs($limited)->get(\route('inventory-counts.history'));

    $response->assertOk();
    $response->assertInertia(static fn($page) => $page
        ->where('store.id', $storeA->getKey())
        ->has('rows', 1)
        ->where('rows.0.id', $sessionA->getKey())
        ->where('is_admin', false));
});

\test('history filters by item id', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $a = Item::factory()->create(['user_id' => $admin->getKey()]);
    $b = Item::factory()->create(['user_id' => $admin->getKey()]);

    $sessionA = InventorySession::factory()->forStore($store)->byUser($admin)->create();
    InventorySessionItem::factory()->create([
        'session_id' => $sessionA->getKey(),
        'item_id' => $a->getKey(),
        'quantity' => 4,
    ]);
    $sessionB = InventorySession::factory()->forStore($store)->byUser($admin)->create();
    InventorySessionItem::factory()->create([
        'session_id' => $sessionB->getKey(),
        'item_id' => $b->getKey(),
        'quantity' => 9,
    ]);

    $response = $this->actingAs($admin)->get(\route('inventory-counts.history', [
        'store_id' => $store->getKey(),
        'item_id' => $a->getKey(),
    ]));

    $response->assertOk();
    $response->assertInertia(static fn($page) => $page
        ->has('rows', 1)
        ->where('rows.0.id', $sessionA->getKey()));
});

\test('limited user without an assigned store is refused', function (): void {
    $limited = Typer::assertInstance(
        UserFactory::new()->createOne(),
        User::class,
    );

    $this->actingAs($limited)
        ->get(\route('inventory-counts.history'))
        ->assertForbidden();
});
