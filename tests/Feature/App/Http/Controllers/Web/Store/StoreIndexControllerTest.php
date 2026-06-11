<?php

declare(strict_types=1);

use App\Models\Store;

\test('guest is redirected from stores to login', function (): void {
    $this->get('/stores')->assertRedirect('/login');
});

\test('authenticated user can view stores index', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    Store::factory()->count(3)->create(['user_id' => $user->getKey()]);

    $response = $this->be($user, 'users')->get('/stores', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'stores/Index');
});

\test('stores index supports search', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    Store::factory()->create(['user_id' => $user->getKey(), 'name' => 'Alpha']);
    Store::factory()->create(['user_id' => $user->getKey(), 'name' => 'Beta']);

    $response = $this->be($user, 'users')->get('/stores?search=alpha', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('props.search', 'alpha');
    \expect($response->json('props.stores'))->toHaveCount(1);
});

\test('stores index excludes other users stores', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    [$other] = \createIsolatedUserWithWarehouse();
    Store::factory()->create(['user_id' => $other->getKey(), 'name' => 'Other Store']);

    $response = $this->be($user, 'users')->get('/stores', $this->inertiaHeaders());

    $names = \array_column($response->json('props.stores'), 'name');
    \expect($names)->not->toContain('Other Store');
});
