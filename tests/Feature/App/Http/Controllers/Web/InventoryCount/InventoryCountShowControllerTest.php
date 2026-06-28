<?php

declare(strict_types=1);

use App\Models\InventorySession;
use App\Models\InventorySessionItem;
use App\Models\Item;
use App\Models\Store;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('admin can open a session and see items sorted alphabetically', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $zeta = Item::factory()->create(['user_id' => $admin->getKey(), 'title' => 'Zeta Item']);
    $alpha = Item::factory()->create(['user_id' => $admin->getKey(), 'title' => 'Alpha Item']);

    $previous = InventorySession::factory()->forStore($store)->byUser($admin)->create([
        'counted_at' => Illuminate\Support\Carbon::now()->subDays(2),
    ]);
    InventorySessionItem::factory()->create([
        'session_id' => $previous->getKey(),
        'item_id' => $alpha->getKey(),
        'quantity' => 6,
    ]);
    $current = InventorySession::factory()->forStore($store)->byUser($admin)->create();
    InventorySessionItem::factory()->create([
        'session_id' => $current->getKey(),
        'item_id' => $alpha->getKey(),
        'quantity' => 8,
    ]);
    InventorySessionItem::factory()->create([
        'session_id' => $current->getKey(),
        'item_id' => $zeta->getKey(),
        'quantity' => 4,
    ]);

    $response = $this->actingAs($admin)->get(\route('inventory-counts.show', [
        'session' => $current->getKey(),
    ]));

    $response->assertOk();
    $response->assertInertia(static fn($page) => $page
        ->component('inventory-counts/Show')
        ->where('session.id', $current->getKey())
        ->where('session.store_id', $store->getKey())
        ->has('rows', 2)
        ->where('rows.0.title', 'Alpha Item')
        ->where('rows.0.current', 8)
        ->where('rows.0.previous', 6)
        ->where('rows.1.title', 'Zeta Item')
        ->where('rows.1.current', 4)
        ->where('rows.1.previous', null));
});

\test('show refuses a session from another user', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    [$other] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $other->getKey()]);
    $session = InventorySession::factory()->forStore($store)->byUser($other)->create();

    $this->actingAs($user)
        ->get(\route('inventory-counts.show', ['session' => $session->getKey()]))
        ->assertNotFound();
});

\test('limited user cannot open a session from a different store', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $storeA = Store::factory()->create(['user_id' => $admin->getKey()]);
    $storeB = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($storeA)->createOne(), User::class);

    $session = InventorySession::factory()->forStore($storeB)->byUser($admin)->create();

    $this->actingAs($limited)
        ->get(\route('inventory-counts.show', ['session' => $session->getKey()]))
        ->assertForbidden();
});

\test('limited user can open a session from their assigned store', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);

    $session = InventorySession::factory()->forStore($store)->byUser($admin)->create();

    $this->actingAs($limited)
        ->get(\route('inventory-counts.show', ['session' => $session->getKey()]))
        ->assertOk();
});
