<?php

declare(strict_types=1);

use App\Models\Item;

\test('guest is redirected from items to login', function (): void {
    $this->get('/items')->assertRedirect('/login');
});

\test('authenticated user can view the items index', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    Item::factory()->count(3)->create(['user_id' => $user->getKey()]);

    $response = $this->be($user, 'users')->get('/items', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'items/Index');
    $response->assertJsonCount(3, 'props.items');
});

\test('items index supports search', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    Item::factory()->create(['user_id' => $user->getKey(), 'title' => 'Matcha Powder']);
    Item::factory()->create(['user_id' => $user->getKey(), 'title' => 'Brown Sugar']);

    $response = $this->be($user, 'users')->get('/items?search=matcha', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('props.search', 'matcha');
    $items = $response->json('props.items');
    \expect($items)->toHaveCount(1);
    \expect($items[0]['title'])->toBe('Matcha Powder');
});
