<?php

declare(strict_types=1);

use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\Store;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('guest is redirected from statements to login', function (): void {
    $this->get('/statements')->assertRedirect('/login');
});

\test('authenticated user can view statements index', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);

    $response = $this->be($user, 'users')->get('/statements', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'statements/Index');
    $response->assertJsonPath('props.filters.store_id', $retail->getKey());
    $response->assertJsonCount(30, 'props.days');
    \expect($response->json('props.statement.id'))->toBeInt();
});

\test('statement is auto-created on first visit for the current month', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);

    \expect(Statement::query()->count())->toBe(0);

    $this->be($user, 'users')->get(
        '/statements?store_id=' . $retail->getKey(),
        $this->inertiaHeaders(),
    )->assertOk();

    \expect(Statement::query()->count())->toBe(1);
    \expect(StatementDay::query()->count())->toBe(30);
});

\test('statement is reused on subsequent visits', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);

    $this->be($user, 'users')->get(
        '/statements?store_id=' . $retail->getKey(),
        $this->inertiaHeaders(),
    )->assertOk();
    $this->be($user, 'users')->get(
        '/statements?store_id=' . $retail->getKey(),
        $this->inertiaHeaders(),
    )->assertOk();

    \expect(Statement::query()->count())->toBe(1);
});

\test('statements index respects requested month', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);

    $response = $this->be($user, 'users')->get(
        '/statements?store_id=' . $store->getKey() . '&year=2025&month=2',
        $this->inertiaHeaders(),
    );

    $response->assertOk();
    $response->assertJsonPath('props.filters.year', 2025);
    $response->assertJsonPath('props.filters.month', 2);
    \expect($response->json('props.days'))->toHaveCount(28);
});

\test('statements index is isolated per user', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    [$other] = \createIsolatedUserWithWarehouse();
    $foreignStore = Store::factory()->create(['user_id' => $other->getKey()]);

    $response = $this->be($user, 'users')->get(
        '/statements?store_id=' . $foreignStore->getKey(),
        $this->inertiaHeaders(),
    );

    $response->assertOk();
    // The foreign store id is rejected by the resolver, which falls back
    // to the requesting user's first owned retail store (the warehouse).
    \expect($response->json('props.filters.store_id'))->toBe($warehouse->getKey());
});

\test('limited user is pinned to their assigned store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $admin->update(['is_admin' => true, 'parent_user_id' => null, 'assigned_store_id' => null]);

    $own = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $other = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($own)->createOne(), User::class);

    $response = $this->be($limited, 'users')->get('/statements', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('props.filters.store_id', $own->getKey());
    $response->assertJsonPath('props.is_admin', false);

    // A `?store_id=` override for a non-assigned store is silently
    // ignored — the resolver always pins limited users to their
    // assigned store.
    $overrideResponse = $this->be($limited, 'users')
        ->get('/statements?store_id=' . $other->getKey(), $this->inertiaHeaders());
    $overrideResponse->assertOk();
    $overrideResponse->assertJsonPath('props.filters.store_id', $own->getKey());
});

\test('limited user without an assigned store is refused', function (): void {
    $limited = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $this->be($limited, 'users')
        ->get('/statements', $this->inertiaHeaders())
        ->assertForbidden();
});
