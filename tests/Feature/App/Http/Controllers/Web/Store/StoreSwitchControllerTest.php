<?php

declare(strict_types=1);

use App\Enums\StoreStatusEnum;
use App\Models\Store;
use App\Models\User;
use App\Support\ActiveStoreResolver;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Resolver;
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
    $response->assertSessionHas(ActiveStoreResolver::SESSION_KEY, $retail->getKey());
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
    $response->assertSessionHas(ActiveStoreResolver::SESSION_KEY, $retail->getKey());
});

\test('same admin keeps independent active stores in separate browser sessions', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $storeA = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
        'name' => 'Machine A store',
    ]);
    $storeB = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
        'name' => 'Machine B store',
    ]);
    $session = Resolver::resolveSessionStore();
    $cookieName = $session->getName();
    $machineA = \str_repeat('a', 40);
    $machineB = \str_repeat('b', 40);

    $this->be($user, 'users')
        ->withCookie($cookieName, $machineA)
        ->post('/stores/switch', ['store_id' => $storeA->getKey()])
        ->assertRedirect()
        ->assertSessionHas(ActiveStoreResolver::SESSION_KEY, $storeA->getKey());

    $session->flush();

    $this->withCookie($cookieName, $machineB)
        ->post('/stores/switch', ['store_id' => $storeB->getKey()])
        ->assertRedirect()
        ->assertSessionHas(ActiveStoreResolver::SESSION_KEY, $storeB->getKey());

    $session->flush();

    $this->withCookie($cookieName, $machineA)
        ->get('/statements', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.filters.store_id', $storeA->getKey());

    $session->flush();

    $this->withCookie($cookieName, $machineB)
        ->get('/statements', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.filters.store_id', $storeB->getKey());
});

\test('statements index picks up the session active store', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
        'name' => 'Alpha retail',
    ]);
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
        'name' => 'Zulu retail',
    ]);

    $this->be($user, 'users')
        ->withSession([ActiveStoreResolver::SESSION_KEY => $retail->getKey()])
        ->get('/statements', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.filters.store_id', $retail->getKey());
});

\test('query override does not replace the session active store', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $sessionStore = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);
    $overrideStore = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);

    $this->be($user, 'users')
        ->withSession([ActiveStoreResolver::SESSION_KEY => $sessionStore->getKey()])
        ->get('/statements?store_id=' . $overrideStore->getKey(), $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.filters.store_id', $overrideStore->getKey())
        ->assertSessionHas(ActiveStoreResolver::SESSION_KEY, $sessionStore->getKey());

    $this->get('/statements', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.filters.store_id', $sessionStore->getKey());
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

    $response->assertRedirect()->assertSessionHasErrors();
});

\test('inactive stores are excluded from switching and active-store fallback', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $active = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
        'status' => StoreStatusEnum::ACTIVE->value,
        'name' => 'Active retail',
    ]);
    $inactive = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
        'status' => StoreStatusEnum::INACTIVE->value,
        'name' => 'Inactive retail',
    ]);

    $switchResponse = $this->be($user, 'users')->post('/stores/switch', ['store_id' => $inactive->getKey()]);

    $switchResponse->assertRedirect();
    \assertInertiaFlash($switchResponse, 'error', \__('Selected store is not available.'));

    $this->withSession([ActiveStoreResolver::SESSION_KEY => $inactive->getKey()])
        ->get('/dashboard', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.active_store.id', $active->getKey())
        ->assertJsonCount(2, 'props.available_stores');
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
        ->withSession([
            '_token' => 'test',
            ActiveStoreResolver::SESSION_KEY => $store->getKey(),
        ])
        ->withHeaders(['X-CSRF-TOKEN' => 'test'])
        ->post('/stores/switch', [
            'store_id' => $store->getKey(),
        ], $this->inertiaHeaders())
        ->assertRedirect('/dashboard')
        ->assertSessionMissing(ActiveStoreResolver::SESSION_KEY);
});

\test('statements index clears a stale session active store pointing at a deleted store', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);
    $doomed = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);

    $doomed->delete();

    $response = $this->be($user, 'users')
        ->withSession([ActiveStoreResolver::SESSION_KEY => $doomed->getKey()])
        ->get('/statements', $this->inertiaHeaders());

    $response->assertOk();
    $response
        ->assertJsonPath('props.filters.store_id', $retail->getKey())
        ->assertSessionMissing(ActiveStoreResolver::SESSION_KEY);
});

\test('statements index clears a session active store owned by another admin', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);
    [$other] = \createIsolatedUserWithWarehouse();
    $foreign = Store::factory()->create([
        'user_id' => $other->getKey(),
        'is_warehouse' => false,
    ]);

    $this->be($user, 'users')
        ->withSession([ActiveStoreResolver::SESSION_KEY => $foreign->getKey()])
        ->get('/statements', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.filters.store_id', $retail->getKey())
        ->assertSessionMissing(ActiveStoreResolver::SESSION_KEY);
});
