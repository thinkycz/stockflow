<?php

declare(strict_types=1);

use App\Models\StockMovement;

\test('guest is redirected from stock movements to login', function (): void {
    $this->get('/stock-movements')->assertRedirect('/login');
});

\test('authenticated user can view stock movement index', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    StockMovement::factory()->count(3)->incoming()->byUser($user)->create(['user_id' => $user->getKey()]);

    $response = $this->be($user, 'users')->get('/stock-movements', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'stock-movements/Index');
    $response->assertJsonCount(3, 'props.movements');
});

\test('stock movement index supports filters', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    StockMovement::factory()->incoming()->byUser($user)->create([
        'user_id' => $user->getKey(),
        'number' => 'IN-2026-0001',
    ]);
    StockMovement::factory()->outgoing(App\Models\Store::factory()->create([
        'user_id' => $user->getKey(),
    ]))->byUser($user)->create([
        'user_id' => $user->getKey(),
        'number' => 'OUT-2026-0001',
    ]);

    $response = $this->be($user, 'users')->get(
        '/stock-movements?type=outgoing',
        $this->inertiaHeaders(),
    );

    \expect($response->json('props.movements'))->toHaveCount(1);
    \expect($response->json('props.movements.0.type'))->toBe('outgoing');
});

\test('stock movement show page is reachable', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $movement = StockMovement::factory()->incoming()->byUser($user)->create(['user_id' => $user->getKey()]);

    $response = $this->be($user, 'users')->get("/stock-movements/{$movement->getKey()}", $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'stock-movements/Show');
    $response->assertJsonPath('props.movement.id', $movement->getKey());
});

\test('stock movement show 404s for another user', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    [$other] = \createIsolatedUserWithWarehouse();
    $otherMovement = StockMovement::factory()->incoming()->byUser($other)->create(['user_id' => $other->getKey()]);

    $this->be($user, 'users')->get("/stock-movements/{$otherMovement->getKey()}")->assertNotFound();
});
