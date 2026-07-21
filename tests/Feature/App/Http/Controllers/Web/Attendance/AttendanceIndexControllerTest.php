<?php

declare(strict_types=1);

use App\Models\Store;
use App\Models\Worker;
use Database\Factories\UserFactory;

\test('admin and assigned limited user can open attendance for the retail store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $limited = UserFactory::new()->limited($store)->createOne();

    $this->be($admin, 'users')->get('/attendance', $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('component', 'attendance/Index')
        ->assertJsonPath('props.workers.0.id', $worker->getKey())
        ->assertJsonPath('props.is_admin', true)
        ->assertJsonMissingPath('props.report');

    $this->be($limited, 'users')->get('/attendance', $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('props.store.id', $store->getKey())
        ->assertJsonPath('props.is_admin', false)->assertJsonMissingPath('props.report');
});
