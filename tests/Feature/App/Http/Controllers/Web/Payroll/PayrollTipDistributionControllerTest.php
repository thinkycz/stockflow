<?php

declare(strict_types=1);

use App\Models\PayrollAdjustment;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Worker;

\test('admin distributes one tip amount between payable workers', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $workers = Worker::factory()->count(2)->create(['user_id' => $admin->getKey()]);
    foreach ([1, 2] as $index => $hours) {
        Shift::factory()->create([
            'user_id' => $admin->getKey(),
            'store_id' => $store->getKey(),
            'worker_id' => $workers[$index]->getKey(),
            'date' => '2026-07-10',
            'start_time' => '08:00',
            'end_time' => \sprintf('%02d:00', 8 + $hours),
        ]);
    }

    $response = $this->be($admin, 'users')->post('/payroll/tip-distributions?store_id=' . $store->getKey(), [
        'year' => 2026,
        'month' => 7,
        'amount' => 90,
    ])->assertRedirect();

    \assertInertiaFlash($response, 'success', \__('Tips distributed proportionally.'));
    \expect(PayrollAdjustment::query()->orderBy('worker_id')->pluck('amount')->all())
        ->toBe(['30.00', '60.00']);
});

\test('tip distribution rejects a month without payable hours', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $index = '/payroll?store_id=' . $store->getKey() . '&year=2026&month=7';

    $this->be($admin, 'users')
        ->from($index)
        ->post(
            '/payroll/tip-distributions?store_id=' . $store->getKey(),
            ['year' => 2026, 'month' => 7, 'amount' => 90],
            [...$this->inertiaHeaders(), 'X-StockFlow-Action' => 'true'],
        )
        ->assertRedirect($index)
        ->assertSessionHasErrors('amount');

    \expect(PayrollAdjustment::query()->count())->toBe(0);
});
