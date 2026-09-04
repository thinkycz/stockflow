<?php

declare(strict_types=1);

use App\Domain\Recipes\RecipeCatalogService;
use App\Domain\Recipes\RecipeTestSessionService;
use App\Models\InventorySession;
use App\Models\StockMovement;
use App\Models\Worker;
use Database\Factories\UserFactory;

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

\test('integrity diagnostic identifies a partially submitted recipe parent without repair', function (): void {
    [$admin, $store] = \createIsolatedUserWithWarehouse();
    (new RecipeCatalogService())->initialize($admin);
    $worker = Worker::factory()->createOne(['user_id' => $admin->getKey()]);
    $session = (new RecipeTestSessionService())->start(UserFactory::new()->limited($store)->createOne(), $worker);
    $attempt = $session->getAttempts()->firstOrFail();
    $attempt->update(['submitted_at' => \now()]);
    $this->artisan('stockflow:integrity:diagnose')->expectsOutputToContain('partially_submitted_recipe_session')->assertFailed();
    \expect($session->fresh()?->getSubmittedAt())->toBeNull()
        ->and($session->attempts()->whereNotNull('submitted_at')->count())->toBe(1);
});
