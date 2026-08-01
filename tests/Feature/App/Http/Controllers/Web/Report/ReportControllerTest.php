<?php

declare(strict_types=1);

use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\Store;
use Database\Factories\UserFactory;

\test('guest is redirected from reports to login', function (): void {
    $this->get('/reports')->assertRedirect('/login');
});

\test('reports expose a unified monthly financial and inventory payload', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();

    $response = $this->be($user, 'users')->get('/reports?year=2026&month=7', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'reports/Index');
    $response->assertJsonStructure([
        'props' => [
            'active_store',
            'filter' => ['store_id', 'year', 'month'],
            'summary' => ['total_revenue', 'gross_margin', 'consumption_cost', 'inventory_value'],
            'financial_report' => ['totals', 'channels', 'daily'],
            'inventory_report' => [
                'as_of',
                'current_inventory',
                'consumption',
                'flows',
                'risk',
                'data_quality',
                'classified_changes',
                'consumption_series',
                'items',
            ],
        ],
    ]);
    $response->assertJsonPath('props.filter.year', 2026);
    $response->assertJsonPath('props.filter.month', 7);
});

\test('reports scope financial data to the active store', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $other = Store::factory()->create(['user_id' => $user->getKey()]);

    $local = Statement::factory()->forStore($warehouse)->forMonth(2026, 7)->create();
    StatementDay::factory()->for($local, 'statement')->create(['date' => '2026-07-01', 'cash' => 100, 'total' => 100]);
    $foreign = Statement::factory()->forStore($other)->forMonth(2026, 7)->create();
    StatementDay::factory()->for($foreign, 'statement')->create(['date' => '2026-07-01', 'cash' => 900, 'total' => 900]);

    $response = $this->be($user, 'users')->get(
        '/reports?store_id=' . $warehouse->getKey() . '&year=2026&month=7',
        $this->inertiaHeaders(),
    );

    \expect((float) $response->json('props.financial_report.totals.total_revenue'))->toBe(100.0);
    \expect($response->json('props.active_store.id'))->toBe($warehouse->getKey());
});

\test('reports render an empty unified payload without an active store', function (): void {
    $user = UserFactory::new()->admin()->createOne();

    $response = $this->be($user, 'users')->get('/reports', $this->inertiaHeaders());

    $response->assertOk();
    \expect($response->json('props.active_store'))->toBeNull();
    \expect((float) $response->json('props.financial_report.totals.total_revenue'))->toBe(0.0);
    \expect((float) $response->json('props.inventory_report.current_inventory.value'))->toBe(0.0);
});
