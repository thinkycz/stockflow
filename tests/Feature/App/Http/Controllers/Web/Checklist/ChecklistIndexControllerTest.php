<?php

declare(strict_types=1);

use App\Models\ChecklistDay;
use App\Models\Store;
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
