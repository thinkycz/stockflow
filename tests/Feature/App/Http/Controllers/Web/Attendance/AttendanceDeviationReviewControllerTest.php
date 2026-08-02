<?php

declare(strict_types=1);

use App\Models\AttendanceDeviationReview;
use App\Models\AttendanceSession;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Worker;
use Database\Factories\UserFactory;

\test('admin can review a fresh attendance deviation and a reason is required', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $shift = Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-10', 'start_time' => '08:00', 'end_time' => '16:00',
    ]);
    AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'shift_id' => $shift->getKey(), 'started_at' => '2026-07-10 06:20:00', 'ended_at' => '2026-07-10 14:30:00',
    ]);
    $payload = [
        'decision' => 'approved', 'reason' => 'Accepted actual coverage',
        'start_time' => '08:15', 'end_time' => '16:30', 'allow_overlap' => false,
        'expected_started_at' => '2026-07-10T08:20:00+02:00',
        'expected_ended_at' => '2026-07-10T16:30:00+02:00',
        'expected_start_time' => '08:00', 'expected_end_time' => '16:00',
    ];

    $this->be($admin, 'users')->post(
        "/attendance/shifts/{$shift->getKey()}/deviation-reviews",
        [...$payload, 'reason' => ''],
        $this->inertiaHeaders(),
    )->assertSessionHasErrors('reason');

    $this->be($admin, 'users')->post(
        "/attendance/shifts/{$shift->getKey()}/deviation-reviews",
        $payload,
        $this->inertiaHeaders(),
    )->assertRedirect('/attendance/report?month=2026-07');

    \expect(AttendanceDeviationReview::query()->count())->toBe(1)
        ->and($shift->refresh()->getStartTimeShort())->toBe('08:15');

    $limited = UserFactory::new()->limited($store)->createOne();
    $this->be($limited, 'users')->post(
        "/attendance/shifts/{$shift->getKey()}/deviation-reviews",
        $payload,
        $this->inertiaHeaders(),
    )->assertNotFound();
});
