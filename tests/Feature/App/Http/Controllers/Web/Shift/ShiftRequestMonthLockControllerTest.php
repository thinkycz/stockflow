<?php

declare(strict_types=1);

use App\Models\ShiftRequestMonthLock;
use App\Models\Store;
use Database\Factories\UserFactory;
use Illuminate\Support\Carbon;

\test('admin can lock and reopen a future request month', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    Carbon::setTestNow('2026-08-07 10:00:00 UTC');
    $url = '/shift-request-month-locks';

    $this->be($admin, 'users')->post($url, ['year' => 2026, 'month' => 9, 'locked' => true])
        ->assertRedirect('/shifts?year=2026&month=9');
    $lock = ShiftRequestMonthLock::query()->first();
    \expect($lock)->not->toBeNull()
        ->and($lock->getStoreId())->toBe($store->getKey())
        ->and($lock->getLockedByUserId())->toBe($admin->getKey());

    $this->be($admin, 'users')->post($url, ['year' => 2026, 'month' => 9, 'locked' => false])
        ->assertRedirect('/shifts?year=2026&month=9');
    \expect(ShiftRequestMonthLock::query()->count())->toBe(0);

    Carbon::setTestNow();
});

\test('month lock rejects current periods and limited users', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $limited = UserFactory::new()->limited($store)->createOne();
    Carbon::setTestNow('2026-08-07 10:00:00 UTC');

    $this->be($admin, 'users')->post('/shift-request-month-locks', ['year' => 2026, 'month' => 8, 'locked' => true], $this->inertiaHeaders())
        ->assertSessionHasErrors('date');
    $this->be($limited, 'users')->post('/shift-request-month-locks', ['year' => 2026, 'month' => 9, 'locked' => true])
        ->assertRedirect('/dashboard');

    Carbon::setTestNow();
});
