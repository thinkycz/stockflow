<?php

declare(strict_types=1);

use App\Models\ShiftShareLink;
use App\Models\Store;
use Database\Factories\UserFactory;

\test('admin can create multiple named public links for the active store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
    ]);

    $this->be($admin, 'users')
        ->post('/shift-share-links?store_id=' . $store->getKey(), ['name' => 'Summer team'], $this->inertiaHeaders())
        ->assertRedirect();
    $this->be($admin, 'users')
        ->post('/shift-share-links?store_id=' . $store->getKey(), ['name' => 'Contractors'], $this->inertiaHeaders())
        ->assertRedirect();

    $links = ShiftShareLink::query()->orderBy('id')->get();

    \expect($links)->toHaveCount(2)
        ->and($links[0]->getName())->toBe('Summer team')
        ->and($links[1]->getName())->toBe('Contractors')
        ->and($links[0]->getToken())->toHaveLength(64)
        ->and($links[1]->getToken())->toHaveLength(64)
        ->and($links[0]->getToken())->not->toBe($links[1]->getToken())
        ->and($links[0]->getStoreId())->toBe($store->getKey());
});

\test('public link names are required trimmed bounded and unique per store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
    ]);

    $this->be($admin, 'users')
        ->post('/shift-share-links?store_id=' . $store->getKey(), ['name' => '  Team link  '], $this->inertiaHeaders())
        ->assertRedirect();

    $this->be($admin, 'users')
        ->post('/shift-share-links?store_id=' . $store->getKey(), ['name' => 'Team link'], $this->inertiaHeaders())
        ->assertSessionHasErrors('name');
    $this->be($admin, 'users')
        ->post('/shift-share-links?store_id=' . $store->getKey(), ['name' => ''], $this->inertiaHeaders())
        ->assertSessionHasErrors('name');
    $this->be($admin, 'users')
        ->post('/shift-share-links?store_id=' . $store->getKey(), ['name' => \str_repeat('a', 101)], $this->inertiaHeaders())
        ->assertSessionHasErrors('name');

    \expect(ShiftShareLink::query()->first()?->getName())->toBe('Team link');
});

\test('limited user cannot create public links', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
    ]);
    $limited = UserFactory::new()->limited($store)->createOne();

    $this->be($limited, 'users')
        ->post('/shift-share-links', ['name' => 'Forbidden'], $this->inertiaHeaders())
        ->assertForbidden();

    \expect(ShiftShareLink::query()->count())->toBe(0);
});
