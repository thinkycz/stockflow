<?php

declare(strict_types=1);

use App\Models\InventorySessionItem;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreItem;
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

\test('inventory draft autosave derives a valid reason instead of rejecting a stale selection', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    StoreItem::query()->create([
        'store_id' => $store->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 10,
    ]);
    $session = \app(InventorySessionService::class)->startDraft($user, $store);

    $this->be($user, 'users')->putJson(\route('inventory-counts.drafts.rows.update', $session), [
        'item_id' => $item->getKey(),
        'quantity' => '12.000',
        'classification' => 'consumption',
        'client_version' => 1,
    ])->assertOk()->assertJsonPath('saved', true);

    $row = InventorySessionItem::query()->where('session_id', $session->getKey())->firstOrFail();
    \expect($row->getClassification()?->value)->toBe('inventory_correction');
});

\test('inventory draft autosave normalizes extra decimal places instead of rejecting the row', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    $session = \app(InventorySessionService::class)->startDraft($user, $store);

    $this->be($user, 'users')->putJson(\route('inventory-counts.drafts.rows.update', $session), [
        'item_id' => $item->getKey(),
        'quantity' => '1.2345',
        'classification' => 'inventory_correction',
        'client_version' => 1,
    ])->assertOk()->assertJsonPath('saved', true);

    \expect(InventorySessionItem::query()->where('session_id', $session->getKey())->firstOrFail()->getQuantity())->toBe(1.235);
});

\test('inventory draft autosave does not reject a long draft note', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    $session = \app(InventorySessionService::class)->startDraft($user, $store);
    $note = \str_repeat('poznámka ', 299) . 'poznámka';

    $this->be($user, 'users')->putJson(\route('inventory-counts.drafts.rows.update', $session), [
        'item_id' => $item->getKey(),
        'quantity' => '1',
        'classification' => 'inventory_correction',
        'note' => $note,
        'client_version' => 1,
    ])->assertOk()->assertJsonPath('saved', true);

    \expect(InventorySessionItem::query()->where('session_id', $session->getKey())->firstOrFail()->getNote())->toBe($note);
});
