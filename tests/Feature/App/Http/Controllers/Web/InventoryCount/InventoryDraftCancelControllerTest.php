<?php

declare(strict_types=1);

use App\Models\Store;
use App\Services\InventorySessionService;

\test('inventory draft cancel controller releases the active draft slot', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $session = \app(InventorySessionService::class)->startDraft($user, $store);

    $this->be($user, 'users')->post(\route('inventory-counts.drafts.cancel', $session))
        ->assertRedirect(\route('inventory-counts.index'));

    \expect($session->fresh()->getStatus())->toBe('cancelled');
    \expect($session->fresh()->getAttribute('active_store_key'))->toBeNull();
});
