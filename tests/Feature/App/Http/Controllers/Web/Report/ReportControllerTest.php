<?php

declare(strict_types=1);

use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\Store;
use Database\Factories\UserFactory;

\test('guest is redirected from reports to login', function (): void {
    $this->get('/reports')->assertRedirect('/login');
});

\test('reports expose only the financial statement payload', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();

    $response = $this->be($user, 'users')->get('/reports', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'reports/Index');
    $response->assertJsonStructure([
        'props' => [
            'active_store',
            'statement_report' => ['totals', 'channels', 'daily'],
            'statement_filter',
        ],
    ]);
    $response->assertJsonMissingPath('props.inventory_value');
    $response->assertJsonMissingPath('props.most_moved');
    $response->assertJsonMissingPath('props.adjustments');
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

    \expect((float) $response->json('props.statement_report.totals.total_revenue'))->toBe(100.0);
    \expect($response->json('props.active_store.id'))->toBe($warehouse->getKey());
});

\test('reports render a financial payload without an active store', function (): void {
    $user = UserFactory::new()->admin()->createOne();

    $response = $this->be($user, 'users')->get('/reports', $this->inertiaHeaders());

    $response->assertOk();
    \expect($response->json('props.active_store'))->toBeNull();
    \expect((float) $response->json('props.statement_report.totals.total_revenue'))->toBe(0.0);
});
