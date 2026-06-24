<?php

declare(strict_types=1);

use App\Models\InventorySession;
use App\Models\InventorySessionItem;
use App\Models\Item;
use App\Models\Store;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('session exposes its store, creator and items through explicit relations', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $creator = Typer::assertInstance(UserFactory::new()->admin()->createOne(), App\Models\User::class);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    $session = InventorySession::factory()
        ->forStore($store)
        ->byUser($user)
        ->byCreator($creator)
        ->create();
    InventorySessionItem::factory()->create([
        'session_id' => $session->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 4,
    ]);

    \expect($session->getStore()->getKey())->toBe($store->getKey());
    \expect($session->getCreatedBy())->toBe($creator->getKey());
    \expect($session->items()->count())->toBe(1);
});

\test('session item exposes its session and item through explicit relations', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    $session = InventorySession::factory()->forStore($store)->byUser($user)->create();

    $row = InventorySessionItem::factory()->create([
        'session_id' => $session->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 9,
        'note' => 'morning',
    ]);

    \expect($row->getSession()->getKey())->toBe($session->getKey());
    \expect($row->getItem()->getKey())->toBe($item->getKey());
    \expect($row->getQuantity())->toBe(9);
    \expect($row->getNote())->toBe('morning');
});

\test('session counted_at is cast to a Carbon instance', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $session = InventorySession::factory()->forStore($store)->byUser($user)->create();

    \expect($session->getCountedAt())->toBeInstanceOf(Illuminate\Support\Carbon::class);
});
