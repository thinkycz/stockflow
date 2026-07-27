<?php

declare(strict_types=1);

use App\Models\InventorySession;
use App\Models\Store;

\test('inventory draft start controller creates one resumable draft', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);

    $this->be($user, 'users')->post(\route('inventory-counts.drafts.start'), [
        'store_id' => $store->getKey(),
    ])->assertRedirect(\route('inventory-counts.index'));

    \expect(InventorySession::query()->where('active_store_key', $store->getKey())->count())->toBe(1);
});
