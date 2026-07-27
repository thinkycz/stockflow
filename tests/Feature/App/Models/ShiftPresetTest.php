<?php

declare(strict_types=1);

use App\Models\ShiftPreset;
use App\Models\Store;

\test('shift preset exposes its store scoped schedule', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $preset = ShiftPreset::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'name' => 'Morning',
        'start_time' => '10:00',
        'end_time' => '15:00',
    ]);

    \expect($preset->getUserId())->toBe($admin->getKey())
        ->and($preset->getStoreId())->toBe($store->getKey())
        ->and($preset->getName())->toBe('Morning')
        ->and($preset->getStartTimeShort())->toBe('10:00')
        ->and($preset->getEndTimeShort())->toBe('15:00');
});

\test('shift presets can be scoped to a store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $storeA = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $storeB = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    ShiftPreset::factory()->create(['user_id' => $admin->getKey(), 'store_id' => $storeA->getKey()]);
    ShiftPreset::factory()->create(['user_id' => $admin->getKey(), 'store_id' => $storeB->getKey()]);

    $query = ShiftPreset::query();
    ShiftPreset::scopeForStore($query, $storeA->getKey());

    \expect($query->count())->toBe(1);
});
