<?php

declare(strict_types=1);

use App\Models\Store;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('admin sees themselves and their limited users on the user index', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);

    $otherAdmin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $otherLimited = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $response = $this->actingAs($admin)->get(\route('users.index'));

    $response->assertOk();
    $response->assertInertia(static fn($page) => $page
        ->component('users/Index')
        ->has('users', 2)
        ->where('users.0.email', $admin->getEmail())
        ->where('users.0.is_admin', true)
        ->where('users.1.email', $limited->getEmail())
        ->where('users.1.is_admin', false)
        ->where('users.1.assigned_store_id', $store->getKey())
        ->where('users.1.assigned_store_name', $store->getName())
        ->missing('users.2'));

    \expect($otherAdmin->getEmail())->not->toBeEmpty();
    \expect($otherLimited->getEmail())->not->toBeEmpty();
});

\test('limited user is bounced away from the user index', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);

    $response = $this->actingAs($limited)->get(\route('users.index'));

    $response->assertRedirect('/dashboard');
});

\test('guest is redirected to the login screen', function (): void {
    $response = $this->get(\route('users.index'));

    $response->assertRedirect('/login');
});
