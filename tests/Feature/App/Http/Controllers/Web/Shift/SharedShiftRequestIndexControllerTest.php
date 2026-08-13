<?php

declare(strict_types=1);

use App\Models\ShiftRequest;
use App\Models\ShiftRequestMonthLock;
use App\Models\ShiftShareLink;
use App\Models\Store;
use App\Models\Worker;
use Illuminate\Support\Carbon;

\test('guest sees future requests only for the selected worker', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    ShiftShareLink::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'token' => 'requests-token',
    ]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey(), 'first_name' => 'Anna', 'last_name' => 'Nova']);
    $otherWorker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    ShiftRequest::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-09-10', 'start_time' => '09:00', 'end_time' => '17:00',
    ]);
    ShiftRequest::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $otherWorker->getKey(),
        'date' => '2026-09-11',
    ]);
    ShiftRequestMonthLock::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'year' => 2026, 'month' => 9,
        'locked_by_user_id' => $admin->getKey(),
    ]);
    Carbon::setTestNow('2026-08-07 10:00:00 UTC');

    $response = $this->get("/public/shifts/requests-token/requests?year=2026&month=9&worker_id={$worker->getKey()}", $this->inertiaHeaders());

    $response->assertOk()
        ->assertJsonPath('component', 'public-shift-requests/Index')
        ->assertJsonPath('props.selected_worker_id', $worker->getKey())
        ->assertJsonPath('props.shift_requests.0.date', '2026-09-10')
        ->assertJsonPath('props.shift_requests.0.start_time', '09:00')
        ->assertJsonPath('props.is_locked', true);
    \expect($response->json('props.shift_requests'))->toHaveCount(1);

    Carbon::setTestNow();
});

\test('request page defaults and clamps navigation to next month', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    ShiftShareLink::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'token' => 'requests-token',
    ]);
    Carbon::setTestNow('2026-08-07 10:00:00 UTC');

    $response = $this->get('/public/shifts/requests-token/requests?year=2026&month=8', $this->inertiaHeaders());

    $response->assertOk()
        ->assertJsonPath('props.filters.year', 2026)
        ->assertJsonPath('props.filters.month', 9)
        ->assertJsonPath('props.selected_worker_id', null)
        ->assertJsonCount(0, 'props.shift_requests');

    Carbon::setTestNow();
});

\test('unknown public request token returns not found', function (): void {
    $this->get('/public/shifts/unknown/requests', $this->inertiaHeaders())->assertNotFound();
});
