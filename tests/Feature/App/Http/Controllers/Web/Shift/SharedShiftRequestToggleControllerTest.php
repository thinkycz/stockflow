<?php

declare(strict_types=1);

use App\Models\ShiftRequest;
use App\Models\ShiftRequestMonthLock;
use App\Models\Store;
use App\Models\Worker;
use Database\Factories\UserFactory;
use Illuminate\Support\Carbon;

\test('public request toggle creates replaces and removes one daily request', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $admin->getKey(), 'is_warehouse' => false, 'shift_share_token' => 'requests-token',
    ]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    Carbon::setTestNow('2026-08-07 10:00:00 UTC');
    $url = '/public/shifts/requests-token/requests/toggle';

    $this->postJson($url, [
        'worker_id' => $worker->getKey(), 'date' => '2026-09-10', 'start_time' => '09:00', 'end_time' => '17:00',
    ])->assertCreated()->assertJsonPath('status', 'created')->assertJsonPath('request.start_time', '09:00');

    $this->postJson($url, [
        'worker_id' => $worker->getKey(), 'date' => '2026-09-10', 'start_time' => '10:00', 'end_time' => '18:00',
    ])->assertOk()->assertJsonPath('status', 'updated')->assertJsonPath('request.start_time', '10:00');
    \expect(ShiftRequest::query()->count())->toBe(1);

    $this->postJson($url, [
        'worker_id' => $worker->getKey(), 'date' => '2026-09-10', 'start_time' => '10:00', 'end_time' => '18:00',
    ])->assertOk()->assertJsonPath('status', 'deleted')->assertJsonPath('request', null);
    \expect(ShiftRequest::query()->count())->toBe(0);

    Carbon::setTestNow();
});

\test('public request toggle rejects current months locked months and foreign workers', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $admin->getKey(), 'is_warehouse' => false, 'shift_share_token' => 'requests-token',
    ]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $foreignAdmin = UserFactory::new()->admin()->createOne();
    $foreignWorker = Worker::factory()->create(['user_id' => $foreignAdmin->getKey()]);
    Carbon::setTestNow('2026-08-07 10:00:00 UTC');
    $url = '/public/shifts/requests-token/requests/toggle';

    $this->postJson($url, [
        'worker_id' => $worker->getKey(), 'date' => '2026-08-10', 'start_time' => '09:00', 'end_time' => '17:00',
    ])->assertUnprocessable()->assertJsonValidationErrors('date');

    ShiftRequestMonthLock::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'year' => 2026, 'month' => 9,
        'locked_by_user_id' => $admin->getKey(),
    ]);
    $this->postJson($url, [
        'worker_id' => $worker->getKey(), 'date' => '2026-09-10', 'start_time' => '09:00', 'end_time' => '17:00',
    ])->assertUnprocessable()->assertJsonValidationErrors('date');

    $this->postJson($url, [
        'worker_id' => $foreignWorker->getKey(), 'date' => '2026-10-10', 'start_time' => '09:00', 'end_time' => '17:00',
    ])->assertUnprocessable()->assertJsonValidationErrors('worker_id');

    Carbon::setTestNow();
});

\test('unknown public request toggle token returns not found', function (): void {
    $this->postJson('/public/shifts/unknown/requests/toggle', [])->assertNotFound();
});
