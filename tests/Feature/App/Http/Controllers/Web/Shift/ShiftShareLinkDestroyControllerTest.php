<?php

declare(strict_types=1);

use App\Enums\StoreStatusEnum;
use App\Models\ShiftShareLink;
use App\Models\Store;
use Database\Factories\UserFactory;

\test('admin can revoke one link without affecting another link for the store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $revoked = ShiftShareLink::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'token' => 'revoked-token',
    ]);
    ShiftShareLink::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'token' => 'active-token',
    ]);

    $this->be($admin, 'users')
        ->delete('/shift-share-links/' . $revoked->getKey() . '?store_id=' . $store->getKey(), [], $this->inertiaHeaders())
        ->assertRedirect();

    \expect(ShiftShareLink::query()->where('token', 'revoked-token')->exists())->toBeFalse()
        ->and(ShiftShareLink::query()->where('token', 'active-token')->exists())->toBeTrue();
    $this->get('/public/shifts/revoked-token', $this->inertiaHeaders())->assertNotFound();
    $this->get('/public/shifts/revoked-token/manifest.webmanifest')->assertNotFound();
    $this->get('/public/shifts/revoked-token/requests', $this->inertiaHeaders())->assertNotFound();
    $this->postJson('/public/shifts/revoked-token/requests/toggle')->assertNotFound();
    $this->get('/public/shifts/active-token', $this->inertiaHeaders())->assertOk();
});

\test('admin cannot revoke a link outside the active store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $activeStore = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $otherStore = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $link = ShiftShareLink::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $otherStore->getKey(),
    ]);

    $this->be($admin, 'users')
        ->delete('/shift-share-links/' . $link->getKey() . '?store_id=' . $activeStore->getKey(), [], $this->inertiaHeaders())
        ->assertNotFound();

    \expect($link->fresh())->not->toBeNull();
});

\test('limited user cannot revoke public links', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $link = ShiftShareLink::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(),
    ]);
    $limited = UserFactory::new()->limited($store)->createOne();

    $this->be($limited, 'users')
        ->delete('/shift-share-links/' . $link->getKey(), [], $this->inertiaHeaders())
        ->assertForbidden();

    \expect($link->fresh())->not->toBeNull();
});

\test('inactive stores expose no public shift token endpoints', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
        'status' => StoreStatusEnum::INACTIVE->value,
    ]);
    ShiftShareLink::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'token' => 'inactive-store-token',
    ]);

    $this->get('/public/shifts/inactive-store-token', $this->inertiaHeaders())->assertNotFound();
    $this->get('/public/shifts/inactive-store-token/manifest.webmanifest')->assertNotFound();
    $this->get('/public/shifts/inactive-store-token/requests', $this->inertiaHeaders())->assertNotFound();
    $this->postJson('/public/shifts/inactive-store-token/requests/toggle')->assertNotFound();
});
