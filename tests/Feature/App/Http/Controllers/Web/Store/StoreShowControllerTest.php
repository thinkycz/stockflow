<?php

declare(strict_types=1);

use App\Models\Store;

\test('store show page is reachable', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);

    $response = $this->be($user, 'users')->get("/stores/{$store->getKey()}", $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'stores/Show');
    $response->assertJsonPath('props.store.id', $store->getKey());
});

\test('store show 404s for another user', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    [$other] = \createIsolatedUserWithWarehouse();
    $otherStore = Store::factory()->create(['user_id' => $other->getKey()]);

    $this->be($user, 'users')->get("/stores/{$otherStore->getKey()}")->assertNotFound();
});
