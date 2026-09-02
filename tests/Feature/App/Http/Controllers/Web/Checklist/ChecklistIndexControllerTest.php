<?php

declare(strict_types=1);

use App\Models\ChecklistDay;
use App\Models\ChecklistItem;
use App\Models\Store;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;

\test('admin can open store checklist management and limited user cannot', function (): void {
    $admin = UserFactory::new()->admin()->createOne();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $this->withSession(\activeStoreSession($store));

    $this->be($admin, 'users')->get('/checklists', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'checklists/Index')
        ->assertJsonPath('props.active_store.id', $store->getKey());

    $limited = UserFactory::new()->limited($store)->createOne();
    $this->be($limited, 'users')->get('/checklists', $this->inertiaHeaders())->assertRedirect('/dashboard');
});

\test('checklist history worker selector retains archived workers', function (): void {
    $admin = UserFactory::new()->admin()->createOne();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $active = Worker::factory()->create(['user_id' => $admin->getKey(), 'first_name' => 'Active']);
    $archived = Worker::factory()->create(['user_id' => $admin->getKey(), 'first_name' => 'Archived', 'archived_at' => \now()]);
    $this->withSession(\activeStoreSession($store));

    $this->be($admin, 'users')->get('/checklists', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(2, 'props.workers')
        ->assertJsonFragment(['id' => $active->getKey(), 'name' => $active->getFullName()])
        ->assertJsonFragment(['id' => $archived->getKey(), 'name' => $archived->getFullName()]);
});

\test('inactive store checklist history remains visible and read only', function (): void {
    $admin = UserFactory::new()->admin()->createOne();
    $active = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $inactive = Store::factory()->inactive()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
        'checklists_initialized_at' => null,
    ]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey(), 'archived_at' => \now()]);
    $day = ChecklistDay::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $inactive->getKey(),
        'date' => '2026-09-02',
    ]);
    ChecklistItem::factory()->create([
        'checklist_day_id' => $day->getKey(),
        'completed_by_worker_id' => $worker->getKey(),
        'completed_by_user_id' => $admin->getKey(),
        'completed_at' => CarbonImmutable::now(),
    ]);

    $this->be($admin, 'users')->get('/checklists?store_id=' . $inactive->getKey() . '&day_id=' . $day->getKey() . '&from=2026-09-01&to=2026-09-30', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.active_store.id', $inactive->getKey())
        ->assertJsonPath('props.active_store.is_active', false)
        ->assertJsonPath('props.filters.tab', 'history')
        ->assertJsonPath('props.history.total', 1)
        ->assertJsonPath('props.history_detail.id', $day->getKey())
        ->assertJsonPath('props.workers.0.id', $worker->getKey());

    $this->be($admin, 'users')->put('/checklists/templates', [
        'store_id' => $inactive->getKey(),
        'scope' => 'daily',
        'weekday' => null,
        'shift' => 'morning',
        'tasks' => [['text' => 'Should not save']],
    ])->assertNotFound();
    $this->be($admin, 'users')->put('/checklist-days/' . $day->getKey() . '/excuse', [
        'store_id' => $inactive->getKey(),
        'reason' => 'Should not save',
    ])->assertNotFound();

    \expect($inactive->refresh()->getChecklistsInitializedAt())->toBeNull()
        ->and($day->refresh()->getExcuseReason())->toBeNull()
        ->and(Store::query()->whereKey($active->getKey())->exists())->toBeTrue();
});

\test('history status filter is applied before pagination', function (): void {
    $admin = UserFactory::new()->admin()->createOne();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $this->withSession(\activeStoreSession($store));
    $start = CarbonImmutable::parse('2026-01-01');

    foreach (\range(0, 31) as $offset) {
        $excused = $offset < 31;
        ChecklistDay::query()->insert([
            'user_id' => $admin->getKey(),
            'store_id' => $store->getKey(),
            'date' => $start->addDays($offset)->toDateString(),
            'excused_by_user_id' => $excused ? $admin->getKey() : null,
            'excuse_reason' => $excused ? 'Zavřeno' : null,
            'excused_at' => $excused ? $start->addDays($offset)->setTime(8, 0) : null,
            'created_at' => $start,
            'updated_at' => $start,
        ]);
    }

    $response = $this->be($admin, 'users')->get('/checklists?tab=history&from=2026-01-01&to=2026-02-01&status=excused', $this->inertiaHeaders());

    $response->assertOk()
        ->assertJsonCount(30, 'props.history.data')
        ->assertJsonPath('props.history.total', 31)
        ->assertJsonPath('props.history.last_page', 2);
});
