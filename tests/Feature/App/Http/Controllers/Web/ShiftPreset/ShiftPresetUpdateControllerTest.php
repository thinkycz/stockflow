<?php

declare(strict_types=1);

use App\Models\ShiftPreset;
use App\Models\Store;

\test('admin can update a preset in the active store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $preset = ShiftPreset::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'name' => 'Morning',
    ]);

    $this->be($admin, 'users')->put("/shift-presets/{$preset->getKey()}?store_id={$store->getKey()}", [
        'name' => 'Early',
        'start_time' => '08:00',
        'end_time' => '13:00',
    ], $this->inertiaHeaders())->assertRedirect();

    \expect($preset->refresh()->getName())->toBe('Early')
        ->and($preset->getStartTimeShort())->toBe('08:00')
        ->and($preset->getEndTimeShort())->toBe('13:00');
});

\test('admin cannot update a preset from another active store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $activeStore = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $otherStore = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $preset = ShiftPreset::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $otherStore->getKey(),
    ]);

    $this->be($admin, 'users')->put("/shift-presets/{$preset->getKey()}?store_id={$activeStore->getKey()}", [
        'name' => 'Changed',
        'start_time' => '08:00',
        'end_time' => '13:00',
    ], $this->inertiaHeaders())->assertNotFound();

    \expect($preset->refresh()->getStoreId())->toBe($otherStore->getKey())
        ->and($activeStore->getKey())->not->toBe($otherStore->getKey());
});
