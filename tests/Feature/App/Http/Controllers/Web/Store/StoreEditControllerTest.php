<?php

declare(strict_types=1);

use App\Enums\StoreStatusEnum;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreItem;

\test('store edit form is reachable', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);

    $this->be($user, 'users')->get("/stores/{$store->getKey()}/edit", $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'stores/Edit')
        ->assertJsonPath('props.store.id', $store->getKey());
});

\test('user can update a store', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $user->getKey(),
        'name' => 'Old Name',
        'slack_channel' => '#old-channel',
    ]);

    $this->be($user, 'users')->put("/stores/{$store->getKey()}", [
        'name' => 'New Name',
        'address' => 'Updated',
        'status' => StoreStatusEnum::ACTIVE->value,
        'notes' => null,
        'slack_channel' => ' C9876543210 ',
        'is_warehouse' => false,
    ])->assertRedirect();

    $store->refresh();
    \expect($store->getName())->toBe('New Name');
    \expect($store->getAddress())->toBe('Updated');
    \expect($store->getSlackChannel())->toBe('C9876543210');
});

\test('cannot edit a store belonging to another user', function (): void {
    [$userA] = \createIsolatedUserWithWarehouse();
    [$userB] = \createIsolatedUserWithWarehouse();
    $foreign = Store::factory()->create(['user_id' => $userB->getKey()]);

    $this->be($userA, 'users')
        ->put("/stores/{$foreign->getKey()}", [
            'name' => 'Hacked',
            'status' => StoreStatusEnum::ACTIVE->value,
        ])
        ->assertNotFound();
});

\test('required warehouse cannot be deactivated or demoted', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();

    $this->be($user, 'users')->put("/stores/{$warehouse->getKey()}", [
        'name' => $warehouse->getName(),
        'status' => StoreStatusEnum::INACTIVE->value,
        'is_warehouse' => true,
    ], $this->inertiaHeaders())->assertSessionHasErrors('status');

    $this->be($user, 'users')->put("/stores/{$warehouse->getKey()}", [
        'name' => $warehouse->getName(),
        'status' => StoreStatusEnum::ACTIVE->value,
        'is_warehouse' => false,
    ], $this->inertiaHeaders())->assertSessionHasErrors('is_warehouse');

    \expect($warehouse->refresh()->getStatus())->toBe(StoreStatusEnum::ACTIVE)
        ->and($warehouse->isWarehouse())->toBeTrue();
});

\test('retail store cannot be promoted into a second warehouse', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);

    $this->be($user, 'users')->put("/stores/{$store->getKey()}", [
        'name' => $store->getName(),
        'status' => StoreStatusEnum::ACTIVE->value,
        'is_warehouse' => true,
    ], $this->inertiaHeaders())->assertSessionHasErrors('is_warehouse');

    \expect($store->refresh()->isWarehouse())->toBeFalse();
});

\test('retail store with live work cannot be deactivated through edit', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
        'status' => StoreStatusEnum::ACTIVE->value,
    ]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    StoreItem::query()->create(['store_id' => $store->getKey(), 'item_id' => $item->getKey(), 'quantity' => 1]);

    $this->be($user, 'users')->put("/stores/{$store->getKey()}", [
        'name' => $store->getName(),
        'status' => StoreStatusEnum::INACTIVE->value,
        'is_warehouse' => false,
    ], $this->inertiaHeaders())->assertSessionHasErrors('status');

    \expect($store->refresh()->getStatus())->toBe(StoreStatusEnum::ACTIVE);
});
