<?php

declare(strict_types=1);

use App\Models\Worker;

\test('worker edit form is reachable', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);

    $this->be($admin, 'users')->get("/workers/{$worker->getKey()}/edit", $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'workers/Edit')
        ->assertJsonPath('props.worker.id', $worker->getKey());
});

\test('admin can update a worker', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $worker = Worker::factory()->create([
        'user_id' => $admin->getKey(),
        'first_name' => 'Old',
        'last_name' => 'Name',
        'hourly_rate' => 150,
    ]);

    $response = $this->be($admin, 'users')->put("/workers/{$worker->getKey()}", [
        'first_name' => 'New',
        'last_name' => 'Updated',
        'hourly_rate' => 250.75,
    ]);

    $response->assertRedirect();
    $worker->refresh();
    \expect($worker->getFirstName())->toBe('New');
    \expect($worker->getLastName())->toBe('Updated');
    \expect($worker->getHourlyRate())->toBe(250.75);
});

\test('cannot edit a worker belonging to another admin', function (): void {
    [$userA] = \createIsolatedUserWithWarehouse();
    [$userB] = \createIsolatedUserWithWarehouse();
    $foreign = Worker::factory()->create(['user_id' => $userB->getKey()]);

    $this->be($userA, 'users')
        ->put("/workers/{$foreign->getKey()}", [
            'first_name' => 'Hacked',
            'last_name' => 'Attempt',
            'hourly_rate' => 100,
        ])
        ->assertNotFound();
});

\test('update validates required first name', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);

    $this->be($admin, 'users')
        ->withSession(['_token' => 'test'])
        ->withHeaders(['X-CSRF-TOKEN' => 'test'])
        ->put("/workers/{$worker->getKey()}", [
            'first_name' => '',
            'last_name' => 'Novak',
            'hourly_rate' => 200,
        ], $this->inertiaHeaders())->assertStatus(422);
});
