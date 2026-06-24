<?php

declare(strict_types=1);

use App\Models\Store;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('admin can delete a limited user', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);

    $response = $this->actingAs($admin)->delete(\route('users.destroy', $limited));

    $response->assertRedirect(\route('users.index'));
    \expect(User::query()->whereKey($limited->getKey())->exists())->toBeFalse();
});

\test('admin cannot delete themselves', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);

    $response = $this->actingAs($admin)
        ->withHeaders($this->inertiaHeaders())
        ->delete(\route('users.destroy', $admin))
        ->assertRedirect(\route('users.index'));

    \assertInertiaFlash($response, 'error', \__('You cannot delete the main admin.'));
    \expect(User::query()->whereKey($admin->getKey())->exists())->toBeTrue();
});

\test('admin cannot delete a user from another admin tree', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $otherAdmin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $otherAdmin->getKey(), 'is_warehouse' => false]);
    $foreign = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);

    $response = $this->actingAs($admin)->delete(\route('users.destroy', $foreign));

    $response->assertForbidden();
    \expect(User::query()->whereKey($foreign->getKey())->exists())->toBeTrue();
});
