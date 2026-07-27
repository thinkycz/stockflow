<?php

declare(strict_types=1);

use App\Models\AttendanceSession;
use App\Models\Store;
use App\Models\Worker;
use Database\Factories\UserFactory;

\test('limited user can record arrival only for the assigned store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $limited = UserFactory::new()->limited($store)->createOne();

    $this->be($limited, 'users')->post('/attendance/actions', [
        'worker_id' => $worker->getKey(), 'action' => 'arrival', 'confirm_without_shift' => true,
    ], $this->inertiaHeaders())->assertRedirect('/attendance');

    \expect(AttendanceSession::query()->firstOrFail()->getStoreId())->toBe($store->getKey());
});
