<?php

declare(strict_types=1);

use App\Models\InventorySessionItem;
use App\Models\Item;
use App\Models\Store;
use App\Services\InventorySessionService;

\test('inventory draft row controller autosaves an exact decimal string', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    $session = \app(InventorySessionService::class)->startDraft($user, $store);

    $this->be($user, 'users')->putJson(\route('inventory-counts.drafts.rows.update', $session), [
        'item_id' => $item->getKey(),
        'quantity' => '1.250',
        'classification' => 'inventory_correction',
        'client_version' => 1,
    ])->assertOk()->assertJsonPath('saved', true);

    \expect(InventorySessionItem::query()->where('session_id', $session->getKey())->firstOrFail()->getQuantity())->toBe(1.25);
});
