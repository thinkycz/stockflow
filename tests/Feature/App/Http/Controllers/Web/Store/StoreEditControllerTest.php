<?php

declare(strict_types=1);

use App\Enums\StoreStatusEnum;
use App\Models\Store;

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
    ]);

    $this->be($user, 'users')->put("/stores/{$store->getKey()}", [
        'name' => 'New Name',
        'address' => 'Updated',
        'status' => StoreStatusEnum::ACTIVE->value,
        'notes' => null,
        'is_warehouse' => false,
    ])->assertRedirect();

    $store->refresh();
    \expect($store->getName())->toBe('New Name');
    \expect($store->getAddress())->toBe('Updated');
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
