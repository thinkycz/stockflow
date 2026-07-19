<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\Store;
use App\Services\InventorySessionService;

\test('inventory draft close controller closes saved rows', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    $service = \app(InventorySessionService::class);
    $session = $service->startDraft($user, $store);
    $service->saveDraftRow($user, $session, [
        'item_id' => $item->getKey(),
        'quantity' => '0.001',
        'classification' => 'inventory_correction',
        'client_version' => 1,
    ]);

    $this->be($user, 'users')->post(\route('inventory-counts.drafts.close', $session))
        ->assertRedirect(\route('inventory-counts.show', $session));

    \expect($session->fresh()->getStatus())->toBe('closed');
});
