<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Worker;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('worker create form is reachable', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();

    $this->be($admin, 'users')->get('/workers/create', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'workers/Create');
});

\test('admin can create a worker', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();

    $response = $this->be($admin, 'users')
        ->withSession(['_token' => 'test'])
        ->withHeaders(['X-CSRF-TOKEN' => 'test'])
        ->post('/workers', [
            'first_name' => 'Jan',
            'last_name' => 'Novak',
            'hourly_rate' => 200.50,
        ], $this->inertiaHeaders());

    $response->assertRedirect();
    $worker = Typer::assertInstance(
        Worker::query()->where('first_name', 'Jan')->where('last_name', 'Novak')->first(),
        Worker::class,
    );
    \expect($worker->getUserId())->toBe($admin->getKey());
    \expect($worker->getHourlyRate())->toBe(200.50);
    \assertInertiaFlash($response, 'success', \__('Worker created.'));
});

\test('first name is required', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();

    $this->be($admin, 'users')
        ->withSession(['_token' => 'test'])
        ->withHeaders(['X-CSRF-TOKEN' => 'test'])
        ->post('/workers', [
            'first_name' => '',
            'last_name' => 'Novak',
            'hourly_rate' => 200,
        ], $this->inertiaHeaders())->assertStatus(422);
});

\test('last name is required', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();

    $this->be($admin, 'users')
        ->withSession(['_token' => 'test'])
        ->withHeaders(['X-CSRF-TOKEN' => 'test'])
        ->post('/workers', [
            'first_name' => 'Jan',
            'last_name' => '',
            'hourly_rate' => 200,
        ], $this->inertiaHeaders())->assertStatus(422);
});

\test('hourly rate must be numeric', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();

    $this->be($admin, 'users')
        ->withSession(['_token' => 'test'])
        ->withHeaders(['X-CSRF-TOKEN' => 'test'])
        ->post('/workers', [
            'first_name' => 'Jan',
            'last_name' => 'Novak',
            'hourly_rate' => 'not-a-number',
        ], $this->inertiaHeaders())->assertStatus(422);
});

\test('limited user cannot create workers', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = App\Models\Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);

    $response = $this->actingAs($limited)->post('/workers', [
        'first_name' => 'Jan',
        'last_name' => 'Novak',
        'hourly_rate' => 200,
    ]);

    $response->assertRedirect('/dashboard');
    \expect(Worker::query()->where('first_name', 'Jan')->exists())->toBeFalse();
});
