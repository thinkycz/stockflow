<?php

declare(strict_types=1);

use App\Models\Shift;
use App\Models\Store;
use App\Models\Worker;
use App\Services\ShiftAssignmentService;

\test('assignment service finds exact and overlapping shifts', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $shift = Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-07-15',
        'start_time' => '10:00',
        'end_time' => '15:00',
    ]);
    $service = new ShiftAssignmentService();

    \expect($service->findExact($admin, $store, $worker, '2026-07-15', '10:00', '15:00')?->getKey())
        ->toBe($shift->getKey())
        ->and($service->findOverlaps($admin, $store, $worker, '2026-07-15', '14:00', '18:00'))
        ->toHaveCount(1)
        ->and($service->findOverlaps($admin, $store, $worker, '2026-07-15', '15:00', '21:00'))
        ->toHaveCount(0);
});
