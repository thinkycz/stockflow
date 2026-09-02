<?php

declare(strict_types=1);

use App\Enums\BankStatementStatusEnum;
use App\Enums\StoreStatusEnum;
use App\Models\AttendanceSession;
use App\Models\BankStatement;
use App\Models\ChecklistTemplateTask;
use App\Models\InventorySession;
use App\Models\Item;
use App\Models\Shift;
use App\Models\ShiftRequest;
use App\Models\Statement;
use App\Models\StockMovement;
use App\Models\StockMovementItem;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;

\test('cannot delete a store with inventory', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $user->getKey(),
    ]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    StoreItem::query()->create([
        'store_id' => $store->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 5,
    ]);

    $this->be($user, 'users')
        ->delete("/stores/{$store->getKey()}")
        ->assertStatus(422);

    \expect(Store::query()->where('id', $store->getKey())->exists())->toBeTrue();
});

\test('store with stock movement history is deactivated without losing history', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $user->getKey(),
    ]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    $movement = StockMovement::factory()->incoming()->create([
        'user_id' => $user->getKey(),
        'store_id' => $store->getKey(),
        'created_by' => $user->getKey(),
    ]);

    StockMovementItem::query()->create([
        'stock_movement_id' => $movement->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 10,
        'total' => 10,
        'quantity_before' => 0,
        'quantity_after' => 10,
        'quantity_difference' => 10,
        'adjustment_reason' => null,
    ]);

    $this->be($user, 'users')
        ->delete("/stores/{$store->getKey()}")
        ->assertRedirect('/stores');

    \expect($store->refresh()->getStatus())->toBe(StoreStatusEnum::INACTIVE)
        ->and(StockMovement::query()->whereKey($movement->getKey())->exists())->toBeTrue();
});

\test('store referenced as a movement source is deactivated', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $sourceStore = Store::factory()->create([
        'user_id' => $user->getKey(),
    ]);
    $destinationStore = Store::factory()->create([
        'user_id' => $user->getKey(),
    ]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    $movement = StockMovement::factory()->outgoing($destinationStore)->create([
        'user_id' => $user->getKey(),
        'source_store_id' => $sourceStore->getKey(),
        'created_by' => $user->getKey(),
    ]);

    StockMovementItem::query()->create([
        'stock_movement_id' => $movement->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 5,
        'total' => 5,
        'quantity_before' => 5,
        'quantity_after' => 10,
        'quantity_difference' => 5,
        'adjustment_reason' => null,
    ]);

    $this->be($user, 'users')
        ->delete("/stores/{$sourceStore->getKey()}")
        ->assertRedirect('/stores');

    \expect($sourceStore->refresh()->getStatus())->toBe(StoreStatusEnum::INACTIVE);
});

\test('warehouse removal is always blocked', function (): void {
    [$admin, $warehouse] = \createIsolatedUserWithWarehouse();

    $this->be($admin, 'users')->delete("/stores/{$warehouse->getKey()}")->assertStatus(422);

    \expect($warehouse->refresh()->getStatus())->toBe(StoreStatusEnum::ACTIVE);
});

\test('zero inventory projection rows do not prevent pristine store deletion', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $item = Item::factory()->create(['user_id' => $admin->getKey()]);
    StoreItem::factory()->create(['store_id' => $store->getKey(), 'item_id' => $item->getKey(), 'quantity' => 0]);

    $this->be($admin, 'users')->delete("/stores/{$store->getKey()}")->assertRedirect('/stores');

    \expect(Store::query()->whereKey($store->getKey())->exists())->toBeFalse();
});

\test('statement history deactivates a store instead of cascading financial data', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $statement = Statement::factory()->create(['user_id' => $admin->getKey(), 'store_id' => $store->getKey()]);

    $this->be($admin, 'users')->delete("/stores/{$store->getKey()}")->assertRedirect('/stores');

    \expect($store->refresh()->getStatus())->toBe(StoreStatusEnum::INACTIVE)
        ->and(Statement::query()->whereKey($statement->getKey())->exists())->toBeTrue();
});

\test('checklist configuration deactivates a store instead of cascading it', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $task = ChecklistTemplateTask::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
    ]);

    $this->be($admin, 'users')->delete("/stores/{$store->getKey()}")->assertRedirect('/stores');

    \expect($store->refresh()->getStatus())->toBe(StoreStatusEnum::INACTIVE)
        ->and(ChecklistTemplateTask::query()->whereKey($task->getKey())->exists())->toBeTrue();
});

\test('open inventory work blocks store removal', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    InventorySession::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'status' => 'draft',
        'active_store_key' => $store->getKey(),
        'closed_at' => null,
        'cancelled_at' => null,
    ]);

    $this->be($admin, 'users')->delete("/stores/{$store->getKey()}")->assertStatus(422);

    \expect($store->refresh()->getStatus())->toBe(StoreStatusEnum::ACTIVE);
});

\test('active attendance future shifts and nonterminal bank imports block store removal', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();

    foreach (['attendance', 'shift', 'shift_request', 'bank'] as $blocker) {
        $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);

        if ($blocker === 'attendance') {
            $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
            AttendanceSession::factory()->create([
                'user_id' => $admin->getKey(),
                'store_id' => $store->getKey(),
                'worker_id' => $worker->getKey(),
                'active_worker_id' => $worker->getKey(),
                'ended_at' => null,
                'voided_at' => null,
            ]);
        } elseif ($blocker === 'shift') {
            Shift::factory()->create([
                'user_id' => $admin->getKey(),
                'store_id' => $store->getKey(),
                'worker_id' => Worker::factory()->create(['user_id' => $admin->getKey()])->getKey(),
                'date' => CarbonImmutable::today()->addDay()->toDateString(),
            ]);
        } elseif ($blocker === 'shift_request') {
            ShiftRequest::factory()->create([
                'user_id' => $admin->getKey(),
                'store_id' => $store->getKey(),
                'worker_id' => Worker::factory()->create(['user_id' => $admin->getKey()])->getKey(),
                'date' => CarbonImmutable::today()->addMonth()->toDateString(),
            ]);
        } else {
            BankStatement::factory()->create([
                'user_id' => $admin->getKey(),
                'store_id' => $store->getKey(),
                'uploaded_by_user_id' => $admin->getKey(),
                'status' => BankStatementStatusEnum::PROCESSING->value,
            ]);
        }

        $this->be($admin, 'users')->delete("/stores/{$store->getKey()}")->assertStatus(422);
        \expect($store->refresh()->getStatus())->toBe(StoreStatusEnum::ACTIVE);
    }
});

\test('can delete an empty store with no movement history', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $user->getKey(),
    ]);

    $this->be($user, 'users')
        ->delete("/stores/{$store->getKey()}")
        ->assertRedirect('/stores');

    \expect(Store::query()->where('id', $store->getKey())->exists())->toBeFalse();
});

\test('cannot delete a store assigned to a limited user', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
    ]);
    UserFactory::new()->limited($store)->createOne();

    $this->be($admin, 'users')
        ->delete("/stores/{$store->getKey()}")
        ->assertStatus(422);

    \expect(Store::query()->whereKey($store->getKey())->exists())->toBeTrue();
});
