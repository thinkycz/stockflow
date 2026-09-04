<?php

declare(strict_types=1);

use App\Enums\StoreStatusEnum;
use App\Models\Store;
use App\Models\Worker;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;

\test('admin sees payroll for the active retail store and limited user is denied', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'name' => 'Retail']);

    $this->be($admin, 'users')
        ->get('/payroll?store_id=' . $store->getKey() . '&year=2026&month=7', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'payroll/Index')
        ->assertJsonPath('props.active_store.name', 'Retail')
        ->assertJsonPath('props.payroll_report.status', 'open');

    $limited = UserFactory::new()->limited($store)->createOne();
    $this->be($limited, 'users')->get('/payroll')->assertRedirect('/dashboard');
});

\test('inactive store payroll history remains readable without prospective worker choices', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $active = Store::factory()->create(['user_id' => $admin->getKey(), 'name' => 'Active retail']);
    $store = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'name' => 'Historical retail',
        'status' => StoreStatusEnum::INACTIVE->value,
    ]);
    Worker::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);

    $this->be($admin, 'users')
        ->get('/payroll?store_id=' . $store->getKey() . '&year=2026&month=7', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.active_store.id', $store->getKey())
        ->assertJsonPath('props.active_store.is_active', false)
        ->assertJsonCount(0, 'props.available_workers');

    $this->be($admin, 'users')->post('/payroll/workers', [
        'store_id' => $store->getKey(),
        'year' => 2026,
        'month' => 7,
        'worker_id' => $worker->getKey(),
    ])->assertNotFound();

    \expect(DB::table('payroll_reports')->where('store_id', $active->getKey())->count())->toBe(0)
        ->and(DB::table('payroll_worker_entries')->count())->toBe(0);
});

\test('payroll exposes only workers without a payslip as available', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $included = Worker::factory()->create(['user_id' => $admin->getKey(), 'first_name' => 'Included']);
    $available = Worker::factory()->create(['user_id' => $admin->getKey(), 'first_name' => 'Available']);
    (new App\Domain\Payroll\PayrollReportService())->addWorker($admin, $store, 2026, 7, $included);

    $this->be($admin, 'users')
        ->get('/payroll?store_id=' . $store->getKey() . '&year=2026&month=7', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'props.available_workers')
        ->assertJsonPath('props.available_workers.0.id', $available->getKey());
});

\test('payroll available-worker selector excludes archived workers', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $active = Worker::factory()->create(['user_id' => $admin->getKey(), 'first_name' => 'Active']);
    Worker::factory()->create(['user_id' => $admin->getKey(), 'first_name' => 'Archived', 'archived_at' => \now()]);

    $this->be($admin, 'users')
        ->get('/payroll?store_id=' . $store->getKey() . '&year=2026&month=7', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'props.available_workers')
        ->assertJsonPath('props.available_workers.0.id', $active->getKey());
});
