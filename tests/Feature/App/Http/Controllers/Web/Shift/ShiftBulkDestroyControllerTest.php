<?php

declare(strict_types=1);

use App\Domain\Workforce\WorkforceManagementService;
use App\Enums\OperationalActivityTypeEnum;
use App\Enums\StoreStatusEnum;
use App\Models\AttendanceDeviationReview;
use App\Models\AttendanceSession;
use App\Models\OperationalActivity;
use App\Models\Shift;
use App\Models\ShiftPreset;
use App\Models\ShiftRequest;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use Database\Factories\UserFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * @return array{User, Store, Worker, Shift, Shift}
 */
function bulkShiftFixture(): array
{
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey(), 'attendance_rating_enabled' => false]);
    $attributes = ['user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(), 'date' => '2026-10-15'];

    return [$admin, $store, $worker, Shift::factory()->create($attributes), Shift::factory()->create($attributes)];
}

\test('bulk deletion removes only selected shifts and retains attendance requests and presets', function (): void {
    [$admin, $store, $worker, $first, $second] = \bulkShiftFixture();
    $keep = Shift::factory()->create(['user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(), 'date' => '2026-10-16']);
    $session = AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'shift_id' => $first->getKey(), 'scheduled_date' => '2026-10-15',
        'scheduled_start_time' => '09:00', 'scheduled_end_time' => '10:00', 'hourly_rate' => 150,
    ]);
    $review = AttendanceDeviationReview::query()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'shift_id' => $first->getKey(),
        'actor_user_id' => $admin->getKey(), 'decision' => 'rejected', 'reason' => 'Existing review',
        'actual_started_at' => '2026-10-15 09:00:00', 'actual_ended_at' => '2026-10-15 10:00:00',
        'before_start_time' => '09:00', 'before_end_time' => '10:00', 'after_start_time' => '09:00', 'after_end_time' => '10:00',
    ]);
    $request = ShiftRequest::factory()->create(['user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey()]);
    $preset = ShiftPreset::factory()->create(['user_id' => $admin->getKey(), 'store_id' => $store->getKey()]);

    $response = $this->be($admin, 'users')->post('/shifts/bulk-delete', [
        'store_id' => $store->getKey(), 'year' => 2026, 'month' => 10, 'shift_ids' => [$first->getKey(), $second->getKey()],
    ]);
    $response->assertRedirect('/shifts?store_id=' . $store->getKey() . '&year=2026&month=10');
    \assertInertiaFlash($response, 'success', \__('Selected shifts deleted: :count.', ['count' => 2]));
    $this->assertDatabaseMissing('shifts', ['id' => $first->getKey()]);
    $this->assertDatabaseMissing('shifts', ['id' => $second->getKey()]);
    $this->assertDatabaseHas('shifts', ['id' => $keep->getKey()]);
    $this->assertDatabaseHas('attendance_sessions', [
        'id' => $session->getKey(), 'shift_id' => null,
        'scheduled_start_time' => '09:00', 'scheduled_end_time' => '10:00', 'hourly_rate' => 150,
    ]);
    $this->assertDatabaseMissing('attendance_deviation_reviews', ['id' => $review->getKey()]);
    \expect($session->refresh()->getScheduledDate()?->toDateString())->toBe('2026-10-15');
    $this->assertDatabaseHas('shift_requests', ['id' => $request->getKey()]);
    $this->assertDatabaseHas('shift_presets', ['id' => $preset->getKey()]);
    \expect(OperationalActivity::query()->where('type', OperationalActivityTypeEnum::SHIFT_DELETED->value)->count())->toBe(2);
});

\test('bulk deletion rejects an invalid member without deleting any selected shift', function (string $case): void {
    [$admin, $store, , $first, $second] = \bulkShiftFixture();
    if ($case === 'month') { $second->update(['date' => '2026-11-01']); }
    if ($case === 'store') { $second->update(['store_id' => Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false])->getKey()]); }
    if ($case === 'owner') {
        [$otherAdmin] = \createIsolatedUserWithWarehouse();
        $second->update(['user_id' => $otherAdmin->getKey()]);
    }
    $this->be($admin, 'users')->post('/shifts/bulk-delete', [
        'store_id' => $store->getKey(), 'year' => 2026, 'month' => 10,
        'shift_ids' => [$first->getKey(), $case === 'missing' ? 999999 : $second->getKey()],
    ], $this->inertiaHeaders())->assertSessionHasErrors('shift_ids');
    $this->assertDatabaseHas('shifts', ['id' => $first->getKey()]);
    $this->assertDatabaseHas('shifts', ['id' => $second->getKey()]);
    \expect(OperationalActivity::query()->where('type', OperationalActivityTypeEnum::SHIFT_DELETED->value)->count())->toBe(0);
})->with(['month', 'store', 'owner', 'missing']);

\test('bulk deletion validates the selection and period', function (string $case, string $field): void {
    [$admin, $store, , $first] = \bulkShiftFixture();
    $payload = ['store_id' => $store->getKey(), 'year' => 2026, 'month' => 10, 'shift_ids' => [$first->getKey()]];
    if ($case === 'empty') { $payload['shift_ids'] = []; }
    if ($case === 'duplicate') { $payload['shift_ids'] = [$first->getKey(), $first->getKey()]; }
    if ($case === 'string') { $payload['shift_ids'] = ['invalid']; }
    if ($case === 'month') { $payload['month'] = 13; }
    if ($case === 'year') { $payload['year'] = 0; }
    if ($case === 'store') { unset($payload['store_id']); }
    $this->be($admin, 'users')->post('/shifts/bulk-delete', $payload, $this->inertiaHeaders())->assertSessionHasErrors($field);
    $this->assertDatabaseHas('shifts', ['id' => $first->getKey()]);
})->with([['empty', 'shift_ids'], ['duplicate', 'shift_ids.0'], ['string', 'shift_ids.0'], ['month', 'month'], ['year', 'year'], ['store', 'store_id']]);

\test('bulk deletion rejects limited users and inactive or foreign stores', function (string $case): void {
    [$admin, $store, , $first] = \bulkShiftFixture();
    $actor = $admin;
    if ($case === 'limited') { $actor = UserFactory::new()->limited($store)->createOne(); }
    if ($case === 'inactive') { $store->update(['status' => StoreStatusEnum::INACTIVE->value]); }
    if ($case === 'foreign') {[$actor] = \createIsolatedUserWithWarehouse(); }
    $response = $this->be($actor, 'users')->post('/shifts/bulk-delete', [
        'store_id' => $store->getKey(), 'year' => 2026, 'month' => 10, 'shift_ids' => [$first->getKey()],
    ]);
    if ($case === 'limited') { $response->assertRedirect('/dashboard'); } else { $response->assertNotFound(); }
    $this->assertDatabaseHas('shifts', ['id' => $first->getKey()]);
})->with(['limited', 'inactive', 'foreign']);

\test('bulk deletion rolls back shifts attendance and activities if a later delete fails', function (): void {
    [$admin, $store, $worker, $first, $second] = \bulkShiftFixture();
    $session = AttendanceSession::factory()->create(['user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(), 'shift_id' => $first->getKey()]);
    DB::unprepared('CREATE TRIGGER fail_bulk_shift BEFORE DELETE ON shifts WHEN OLD.id = ' . $second->getKey() . ' BEGIN SELECT RAISE(ABORT, \'forced failure\'); END');
    try {
        \expect(fn() => (new WorkforceManagementService())->deleteShifts($admin, $store, 2026, 10, [$first->getKey(), $second->getKey()]))->toThrow(QueryException::class);
    } finally {
        DB::unprepared('DROP TRIGGER fail_bulk_shift');
    }
    $this->assertDatabaseHas('shifts', ['id' => $first->getKey()]);
    $this->assertDatabaseHas('shifts', ['id' => $second->getKey()]);
    $this->assertDatabaseHas('attendance_sessions', ['id' => $session->getKey(), 'shift_id' => $first->getKey()]);
    \expect(OperationalActivity::query()->where('type', OperationalActivityTypeEnum::SHIFT_DELETED->value)->count())->toBe(0);
});

\test('the complete month can be loaded and deleted beyond one thousand shifts', function (): void {
    [$admin, $store, $worker] = \bulkShiftFixture();
    $rows = \array_fill(0, 1000, [
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-10-20', 'start_time' => '09:00', 'end_time' => '10:00', 'hourly_rate' => 100,
        'created_at' => '2026-10-01 00:00:00', 'updated_at' => '2026-10-01 00:00:00',
    ]);
    foreach (\array_chunk($rows, 100) as $chunk) { Shift::query()->insert($chunk); }
    $this->be($admin, 'users')->get('/shifts?store_id=' . $store->getKey() . '&year=2026&month=10', $this->inertiaHeaders())
        ->assertOk()->assertJsonCount(1002, 'props.shifts');
    $this->post('/shifts/bulk-delete', [
        'store_id' => $store->getKey(), 'year' => 2026, 'month' => 10,
        'shift_ids' => Shift::query()->where('store_id', $store->getKey())->pluck('id')->all(),
    ])->assertSessionHasNoErrors()->assertRedirect();
    \expect(Shift::query()->where('store_id', $store->getKey())->count())->toBe(0);
});
