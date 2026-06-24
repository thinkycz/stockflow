<?php

declare(strict_types=1);

use App\Models\Store;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('admin can create a limited user', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
    ]);

    $response = $this->actingAs($admin)->post(\route('users.store'), [
        'email' => 'staff@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'assigned_store_id' => $store->getKey(),
    ]);

    $response->assertRedirect(\route('users.index'));

    $user = Typer::assertInstance(
        User::query()->where('email', 'staff@example.com')->first(),
        User::class,
    );

    \expect($user->isAdmin())->toBeFalse();
    \expect($user->getParentUserId())->toBe($admin->getKey());
    \expect($user->getAssignedStoreId())->toBe($store->getKey());

    // Sanity check: the admin row is still there, the new user is there.
    \expect(User::query()->whereKey($admin->getKey())->exists())->toBeTrue();
    \expect(User::query()->whereKey($user->getKey())->exists())->toBeTrue();
});

\test('assigned_store_id must reference an admin-owned store', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $otherAdmin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $foreignStore = Store::factory()->create([
        'user_id' => $otherAdmin->getKey(),
        'is_warehouse' => false,
    ]);

    $response = $this->actingAs($admin)
        ->withHeaders($this->inertiaHeaders())
        ->post(\route('users.store'), [
            'email' => 'staff@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'assigned_store_id' => $foreignStore->getKey(),
        ])
        ->assertStatus(422);

    \expect($response->json('props.errors.assigned_store_id'))->toBeArray();
    \expect(User::query()->where('email', 'staff@example.com')->exists())->toBeFalse();
});

\test('password must be confirmed and at least 8 characters', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);

    $response = $this->actingAs($admin)
        ->withHeaders($this->inertiaHeaders())
        ->post(\route('users.store'), [
            'email' => 'staff@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
            'assigned_store_id' => $store->getKey(),
        ])
        ->assertStatus(422);

    \expect($response->json('props.errors.password'))->toBeArray();
});

\test('email must be unique', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    Typer::assertInstance(UserFactory::new()->createOne(['email' => 'taken@example.com']), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);

    $response = $this->actingAs($admin)
        ->withHeaders($this->inertiaHeaders())
        ->post(\route('users.store'), [
            'email' => 'taken@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'assigned_store_id' => $store->getKey(),
        ])
        ->assertStatus(422);

    \expect($response->json('props.errors.email'))->toBeArray();
});

\test('limited user cannot create other users', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);

    $response = $this->actingAs($limited)->post(\route('users.store'), [
        'email' => 'colleague@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'assigned_store_id' => $store->getKey(),
    ]);

    $response->assertRedirect('/dashboard');
    \expect(User::query()->where('email', 'colleague@example.com')->exists())->toBeFalse();
});
