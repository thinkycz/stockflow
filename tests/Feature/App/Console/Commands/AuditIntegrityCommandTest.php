<?php

declare(strict_types=1);

use App\Models\InventorySession;
use App\Models\StockMovement;

\test('integrity diagnostic reports cancelled posted sessions without mutating them', function (): void {
    [$admin, $store] = \createIsolatedUserWithWarehouse();
    $session = InventorySession::factory()->forStore($store)->byUser($admin)->create(['status' => 'cancelled']);
    StockMovement::factory()->create(['user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'inventory_session_id' => $session->getKey()]);
    $this->artisan('stockflow:integrity:diagnose')->expectsOutputToContain('cancelled_inventory_posted')->assertFailed();
    \expect($session->fresh()->getStatus())->toBe('cancelled')
        ->and(StockMovement::query()->count())->toBe(1);
});

\test('integrity diagnostic succeeds for clean history', function (): void {
    $this->artisan('stockflow:integrity:diagnose')->assertSuccessful();
});
