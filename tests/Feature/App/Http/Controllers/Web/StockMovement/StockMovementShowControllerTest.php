<?php

declare(strict_types=1);

use App\Models\StockMovement;

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
