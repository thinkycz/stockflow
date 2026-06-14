<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\StoreItem;

\test('authenticated user can create an item', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();

    $response = $this->be($user, 'users')
        ->withSession(['_token' => 'test'])
        ->withHeaders(['X-CSRF-TOKEN' => 'test'])
        ->post('/items', [
            'title' => 'Test Item',
            'sku' => 'TEST-001',
            'unit' => 'pcs',
            'purchase_price' => '9.99',
            'description' => 'Sample',
        ], $this->inertiaHeaders());

    $response->assertRedirect();
    $item = Item::query()->where('title', 'Test Item')->first();
    \expect($item)->not->toBeNull();
    \expect($item->getSku())->toBe('TEST-001');
    \expect(StoreItem::query()->where('store_id', $warehouse->getKey())->where('item_id', $item->getKey())->exists())->toBeTrue();
});

\test('item create validates required title', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();

    $this->be($user, 'users')
        ->withSession(['_token' => 'test'])
        ->withHeaders(['X-CSRF-TOKEN' => 'test'])
        ->post('/items', [
            'purchase_price' => '9.99',
        ], $this->inertiaHeaders())->assertStatus(422);
});
