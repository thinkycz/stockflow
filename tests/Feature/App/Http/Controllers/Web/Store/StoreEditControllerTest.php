<?php

declare(strict_types=1);

use App\Enums\StoreStatusEnum;
use App\Models\AttendanceSession;
use App\Models\BankStatement;
use App\Models\InventorySession;
use App\Models\Item;
use App\Models\Shift;
use App\Models\ShiftRequest;
use App\Models\ShiftRequestMonthLock;
use App\Models\Statement;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;

\test('store edit form is reachable', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);

    $this->be($user, 'users')->get("/stores/{$store->getKey()}/edit", $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'stores/Edit')
        ->assertJsonPath('props.store.id', $store->getKey());
});

\test('user can update a store', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $user->getKey(),
        'name' => 'Old Name',
        'slack_channel' => '#old-channel',
    ]);

    $this->be($user, 'users')->put("/stores/{$store->getKey()}", [
        'name' => 'New Name',
        'address' => 'Updated',
        'status' => StoreStatusEnum::ACTIVE->value,
        'notes' => null,
        'slack_channel' => ' C9876543210 ',
        'is_warehouse' => false,
    ])->assertRedirect();

    $store->refresh();
    \expect($store->getName())->toBe('New Name');
    \expect($store->getAddress())->toBe('Updated');
    \expect($store->getSlackChannel())->toBe('C9876543210');
});

\test('cannot edit a store belonging to another user', function (): void {
    [$userA] = \createIsolatedUserWithWarehouse();
    [$userB] = \createIsolatedUserWithWarehouse();
    $foreign = Store::factory()->create(['user_id' => $userB->getKey()]);

    $this->be($userA, 'users')
        ->put("/stores/{$foreign->getKey()}", [
            'name' => 'Hacked',
            'status' => StoreStatusEnum::ACTIVE->value,
        ])
        ->assertNotFound();
});

\test('required warehouse cannot be deactivated or demoted', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();

    $this->be($user, 'users')->put("/stores/{$warehouse->getKey()}", [
        'name' => $warehouse->getName(),
        'status' => StoreStatusEnum::INACTIVE->value,
        'is_warehouse' => true,
    ], $this->inertiaHeaders())->assertSessionHasErrors('status');

    $this->be($user, 'users')->put("/stores/{$warehouse->getKey()}", [
        'name' => $warehouse->getName(),
        'status' => StoreStatusEnum::ACTIVE->value,
        'is_warehouse' => false,
    ], $this->inertiaHeaders())->assertSessionHasErrors('is_warehouse');

    \expect($warehouse->refresh()->getStatus())->toBe(StoreStatusEnum::ACTIVE)
        ->and($warehouse->isWarehouse())->toBeTrue();
});

\test('retail store cannot be promoted into a second warehouse', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);

    $this->be($user, 'users')->put("/stores/{$store->getKey()}", [
        'name' => $store->getName(),
        'status' => StoreStatusEnum::ACTIVE->value,
        'is_warehouse' => true,
    ], $this->inertiaHeaders())->assertSessionHasErrors('is_warehouse');

    \expect($store->refresh()->isWarehouse())->toBeFalse();
});

\test('retail store deactivation preserves stock history and the assigned account', function (int $quantity): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
        'status' => StoreStatusEnum::ACTIVE->value,
    ]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    $stock = StoreItem::query()->create(['store_id' => $store->getKey(), 'item_id' => $item->getKey(), 'quantity' => $quantity]);
    $assignedUser = UserFactory::new()->limited($store)->createOne();
    $statement = Statement::factory()->forStore($store)->create();

    $this->be($user, 'users')->withSession(\activeStoreSession($store))->put("/stores/{$store->getKey()}", [
        'name' => $store->getName(),
        'status' => StoreStatusEnum::INACTIVE->value,
        'is_warehouse' => false,
    ], $this->inertiaHeaders())->assertSessionHasNoErrors()->assertRedirect("/stores/{$store->getKey()}");

    \expect($store->refresh()->getStatus())->toBe(StoreStatusEnum::INACTIVE)
        ->and($stock->refresh()->getQuantity())->toBe($quantity)
        ->and($assignedUser->refresh()->getAssignedStoreId())->toBe($store->getKey())
        ->and(Statement::query()->whereKey($statement->getKey())->exists())->toBeTrue();

    $this->get("/stores/{$store->getKey()}", $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.active_store.id', $warehouse->getKey())
        ->assertJsonCount(1, 'props.available_stores');

    $this->be($assignedUser, 'users')->get('/dashboard', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.active_store', null)
        ->assertJsonPath('props.available_stores', []);
})->with([5, -1]);

\test('store edit identifies the operational work blocking deactivation', function (string $blocker, string $reason): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $user->update(['locale' => 'cs']);
    $store = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);

    if ($blocker === 'inventory') {
        InventorySession::factory()->forStore($store)->create([
            'status' => 'draft',
            'active_store_key' => $store->getKey(),
            'closed_at' => null,
        ]);
    } elseif ($blocker === 'attendance') {
        $worker = Worker::factory()->create(['user_id' => $user->getKey()]);
        AttendanceSession::factory()->create([
            'user_id' => $user->getKey(),
            'store_id' => $store->getKey(),
            'worker_id' => $worker->getKey(),
            'active_worker_id' => $worker->getKey(),
            'ended_at' => null,
            'voided_at' => null,
        ]);
    } elseif ($blocker === 'shift') {
        Shift::factory()->create([
            'user_id' => $user->getKey(),
            'store_id' => $store->getKey(),
            'worker_id' => Worker::factory()->create(['user_id' => $user->getKey()])->getKey(),
            'date' => CarbonImmutable::today('Europe/Prague')->addDay()->toDateString(),
        ]);
    } else {
        BankStatement::factory()->create([
            'user_id' => $user->getKey(),
            'store_id' => $store->getKey(),
            'uploaded_by_user_id' => $user->getKey(),
            'status' => $blocker,
        ]);
    }

    $this->be($user, 'users')->get("/stores/{$store->getKey()}/edit", $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.deactivation_blockers', [$reason]);

    $this->put("/stores/{$store->getKey()}", [
        'name' => 'Must not be saved',
        'status' => StoreStatusEnum::INACTIVE->value,
        'is_warehouse' => false,
    ], $this->inertiaHeaders())->assertSessionHasErrors([
        'status' => 'Před deaktivací provozovny vyřešte: ' . $reason . '.',
    ]);

    \expect($store->refresh()->getStatus())->toBe(StoreStatusEnum::ACTIVE)
        ->and($store->getName())->not->toBe('Must not be saved');
})->with([
    'draft inventory' => ['inventory', 'Rozpracované inventury: 1'],
    'open attendance' => ['attendance', 'Otevřená docházka: 1'],
    'future shift' => ['shift', 'Dnešní a budoucí směny: 1'],
    'queued bank import' => ['queued', 'Nedokončené importy bankovních výpisů: 1'],
    'processing bank import' => ['processing', 'Nedokončené importy bankovních výpisů: 1'],
    'bank import under review' => ['review', 'Nedokončené importy bankovních výpisů: 1'],
]);

\test('store deactivation preserves fourteen current and future requests including a locked month', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey(), 'archived_at' => CarbonImmutable::now()]);
    $today = CarbonImmutable::today('Europe/Prague');
    for ($i = 0; $i < 14; ++$i) {
        ShiftRequest::factory()->create([
            'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
            'date' => $today->addDays($i)->toDateString(),
        ]);
    }
    $lock = ShiftRequestMonthLock::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(),
        'year' => $today->year, 'month' => $today->month, 'locked_by_user_id' => $admin->getKey(),
    ]);
    $this->be($admin, 'users')->get("/stores/{$store->getKey()}/edit", $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('props.deactivation_blockers', []);
    $this->put("/stores/{$store->getKey()}", [
        'name' => $store->getName(), 'status' => StoreStatusEnum::INACTIVE->value, 'is_warehouse' => false,
    ], $this->inertiaHeaders())->assertSessionHasNoErrors()->assertRedirect();
    \expect($store->refresh()->isActive())->toBeFalse();
    $this->assertDatabaseCount('shift_requests', 14);
    $this->assertDatabaseHas('shift_request_month_locks', ['id' => $lock->getKey()]);
    $this->put("/stores/{$store->getKey()}", [
        'name' => $store->getName(), 'status' => StoreStatusEnum::ACTIVE->value, 'is_warehouse' => false,
    ], $this->inertiaHeaders())->assertSessionHasNoErrors();
    \expect($store->refresh()->isActive())->toBeTrue();
    $this->assertDatabaseCount('shift_requests', 14);
});

\test('completed operations and another stores live work do not block deactivation', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);
    $otherStore = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);
    InventorySession::factory()->forStore($otherStore)->create([
        'status' => 'draft',
        'active_store_key' => $otherStore->getKey(),
        'closed_at' => null,
    ]);
    $inventory = InventorySession::factory()->forStore($store)->create(['status' => 'closed']);
    $shift = Shift::factory()->create([
        'user_id' => $user->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => Worker::factory()->create(['user_id' => $user->getKey()])->getKey(),
        'date' => CarbonImmutable::today('Europe/Prague')->subDay()->toDateString(),
    ]);

    $this->be($user, 'users')->put("/stores/{$store->getKey()}", [
        'name' => $store->getName(),
        'status' => StoreStatusEnum::INACTIVE->value,
        'is_warehouse' => false,
    ], $this->inertiaHeaders())->assertSessionHasNoErrors()->assertRedirect();

    \expect($store->refresh()->getStatus())->toBe(StoreStatusEnum::INACTIVE)
        ->and($otherStore->refresh()->getStatus())->toBe(StoreStatusEnum::ACTIVE)
        ->and(InventorySession::query()->whereKey($inventory->getKey())->exists())->toBeTrue()
        ->and(Shift::query()->whereKey($shift->getKey())->exists())->toBeTrue();
});
