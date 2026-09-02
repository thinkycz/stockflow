<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Worker;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('admin sees their workers on the index', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $worker = Worker::factory()->create([
        'user_id' => $admin->getKey(),
        'first_name' => 'Jan',
        'last_name' => 'Novak',
        'hourly_rate' => 200.50,
        'attendance_rating_enabled' => false,
    ]);

    $otherAdmin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    Worker::factory()->create(['user_id' => $otherAdmin->getKey()]);

    $response = $this->actingAs($admin)->get(\route('workers.index'));

    $response->assertOk();
    $response->assertInertia(static fn($page) => $page
        ->component('workers/Index')
        ->has('workers', 1)
        ->where('workers.0.id', $worker->getKey())
        ->where('workers.0.first_name', 'Jan')
        ->where('workers.0.last_name', 'Novak')
        ->where('workers.0.color', $worker->getCalendarColor())
        ->where('workers.0.hourly_rate', 200.50)
        ->where('workers.0.attendance_rating_enabled', false)
        ->missing('workers.1'));
});

\test('admin can search workers by name', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    Worker::factory()->create(['user_id' => $admin->getKey(), 'first_name' => 'Jan', 'last_name' => 'Novak']);
    Worker::factory()->create(['user_id' => $admin->getKey(), 'first_name' => 'Petr', 'last_name' => 'Svoboda']);

    $response = $this->actingAs($admin)->get(\route('workers.index', ['search' => 'Novak']));

    $response->assertOk();
    $response->assertInertia(static fn($page) => $page
        ->has('workers', 1)
        ->where('workers.0.last_name', 'Novak'));
});

\test('worker index defaults to active workers and can filter archived workers', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $active = Worker::factory()->create(['user_id' => $admin->getKey(), 'first_name' => 'Active']);
    $archived = Worker::factory()->create(['user_id' => $admin->getKey(), 'first_name' => 'Archived', 'archived_at' => \now()]);

    $this->actingAs($admin)->get(\route('workers.index'))
        ->assertInertia(static fn($page) => $page
            ->has('workers', 1)
            ->where('workers.0.id', $active->getKey())
            ->where('status', 'active'));

    $this->actingAs($admin)->get(\route('workers.index', ['status' => 'archived']))
        ->assertInertia(static fn($page) => $page
            ->has('workers', 1)
            ->where('workers.0.id', $archived->getKey())
            ->where('workers.0.archived', true)
            ->where('status', 'archived'));
});

\test('limited user is bounced away from the workers index', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = App\Models\Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);

    $response = $this->actingAs($limited)->get(\route('workers.index'));

    $response->assertRedirect('/dashboard');
});

\test('guest is redirected to the login screen', function (): void {
    $response = $this->get(\route('workers.index'));

    $response->assertRedirect('/login');
});
