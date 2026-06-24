<?php

declare(strict_types=1);

use App\Models\Store;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('admin can update a limited user', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $storeA = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $storeB = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($storeA)->createOne(), User::class);

    $response = $this->actingAs($admin)->put(\route('users.update', $limited), [
        'email' => 'renamed@example.com',
        'assigned_store_id' => $storeB->getKey(),
    ]);

    $response->assertRedirect(\route('users.index'));

    $limited->refresh();
    \expect($limited->getEmail())->toBe('renamed@example.com');
    \expect($limited->getAssignedStoreId())->toBe($storeB->getKey());
});

\test('admin cannot transfer a limited user to a foreign store', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $otherAdmin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $storeA = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $foreign = Store::factory()->create(['user_id' => $otherAdmin->getKey(), 'is_warehouse' => false]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($storeA)->createOne(), User::class);

    $response = $this->actingAs($admin)
        ->withHeaders($this->inertiaHeaders())
        ->put(\route('users.update', $limited), [
            'email' => $limited->getEmail(),
            'assigned_store_id' => $foreign->getKey(),
        ])
        ->assertStatus(422);

    \expect($response->json('props.errors.assigned_store_id'))->toBeArray();
    $limited->refresh();
    \expect($limited->getAssignedStoreId())->toBe($storeA->getKey());
});

\test('admin can update their own email and password without losing admin role', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);

    $response = $this->actingAs($admin)->put(\route('users.update', $admin), [
        'email' => 'new-admin@example.com',
        'password' => 'new-secret-9',
        'password_confirmation' => 'new-secret-9',
    ]);

    $response->assertRedirect(\route('users.index'));

    $admin->refresh();
    \expect($admin->getEmail())->toBe('new-admin@example.com');
    \expect($admin->isAdmin())->toBeTrue();
    \expect($admin->getParentUserId())->toBeNull();
    \expect($admin->getAssignedStoreId())->toBeNull();
});

\test('admin cannot edit a user that is not in their tree', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $otherAdmin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $otherAdmin->getKey(), 'is_warehouse' => false]);
    $foreign = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);

    $response = $this->actingAs($admin)->get(\route('users.edit', $foreign));

    $response->assertForbidden();
});
