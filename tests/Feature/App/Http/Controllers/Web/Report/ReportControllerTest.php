<?php

declare(strict_types=1);

use App\Models\StockMovement;

\test('guest is redirected from reports to login', function (): void {
    $this->get('/reports')->assertRedirect('/login');
});

\test('authenticated user can view reports', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();

    $response = $this->be($user, 'users')->get('/reports', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'reports/Index');
    $response->assertJsonStructure([
        'props' => [
            'inventory_value',
            'monthly' => ['incoming', 'outgoing'],
            'store_consumption',
            'most_moved',
            'adjustments',
            'reasons',
        ],
    ]);
});

\test('reports only show own data', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    [$other] = \createIsolatedUserWithWarehouse();
    StockMovement::factory()->incoming()->byUser($other)->create(['user_id' => $other->getKey()]);

    $response = $this->be($user, 'users')->get('/reports', $this->inertiaHeaders());

    \expect((float) $response->json('props.inventory_value'))->toBe(0.0);
});
