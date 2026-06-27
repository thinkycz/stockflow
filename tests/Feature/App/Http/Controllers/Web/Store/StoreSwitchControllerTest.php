<?php

declare(strict_types=1);

use App\Models\Store;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('admin can switch the active store', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);

    $response = $this->be($user, 'users')
        ->withSession(['_token' => 'test'])
        ->withHeaders(['X-CSRF-TOKEN' => 'test'])
        ->post('/stores/switch', [
            'store_id' => $retail->getKey(),
        ], $this->inertiaHeaders());

    $response->assertRedirect();
    $this->assertSame($retail->getKey(), $user->fresh()->getActiveStoreId());
});

\test('admin store switch returns JSON when requested', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);

    $response = $this->be($user, 'users')
        ->withSession(['_token' => 'test'])
        ->withHeaders(['X-CSRF-TOKEN' => 'test', 'Accept' => 'application/json'])
        ->post('/stores/switch', [
            'store_id' => $retail->getKey(),
        ]);

    $response->assertOk();
    $response->assertJsonPath('active_store.id', $retail->getKey());
    $this->assertSame($retail->getKey(), $user->fresh()->getActiveStoreId());
});

\test('statements index picks up the persisted active store', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);

    $user->setActiveStoreId($retail->getKey());

    $this->be($user, 'users')
        ->get('/statements', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.filters.store_id', $retail->getKey());
});

\test('admin cannot switch to a store they do not own', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    [$other] = \createIsolatedUserWithWarehouse();
    $foreign = Store::factory()->create([
        'user_id' => $other->getKey(),
        'is_warehouse' => false,
    ]);

    $response = $this->be($user, 'users')
        ->withSession(['_token' => 'test'])
        ->withHeaders(['X-CSRF-TOKEN' => 'test'])
        ->post('/stores/switch', [
            'store_id' => $foreign->getKey(),
        ], $this->inertiaHeaders());

    $response->assertStatus(422);
});

\test('limited user is rejected when calling the switch endpoint', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
    ]);
    $limited = Typer::assertInstance(
        UserFactory::new()->limited($store)->createOne(),
        User::class,
    );

    $this->be($limited, 'users')
        ->withSession(['_token' => 'test'])
        ->withHeaders(['X-CSRF-TOKEN' => 'test'])
        ->post('/stores/switch', [
            'store_id' => $store->getKey(),
        ], $this->inertiaHeaders())
        ->assertRedirect('/dashboard');

    $this->assertNull($limited->fresh()->getActiveStoreId());
});

\test('statements index ignores a stale active store pointing at a deleted store', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);
    $doomed = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);

    // Point the user at the doomed store, then delete it.
    $user->setActiveStoreId($doomed->getKey());
    $doomed->delete();

    $response = $this->be($user, 'users')
        ->get('/statements', $this->inertiaHeaders());

    $response->assertOk();
    // The resolver falls back to the first owned retail store.
    $response->assertJsonPath('props.filters.store_id', $retail->getKey());
});
