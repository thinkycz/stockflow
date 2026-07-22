<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\Store;
use App\Models\User;
use App\Services\InventorySessionService;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

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

\test('limited user can autosave and close a draft for their assigned store', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);
    $item = Item::factory()->create(['user_id' => $admin->getKey()]);
    $session = \app(InventorySessionService::class)->startDraft($limited, $store);

    $this->be($limited, 'users')->putJson(\route('inventory-counts.drafts.rows.update', $session), [
        'item_id' => $item->getKey(),
        'quantity' => '2.500',
        'classification' => 'inventory_correction',
        'client_version' => 1,
    ])->assertOk();

    $this->be($limited, 'users')->post(\route('inventory-counts.drafts.close', $session))
        ->assertRedirect(\route('inventory-counts.show', $session));

    \expect($session->fresh()->getStatus())->toBe('closed');
});

\test('limited user cannot mutate a draft for another store', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $assignedStore = Store::factory()->create(['user_id' => $admin->getKey()]);
    $otherStore = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($assignedStore)->createOne(), User::class);
    $item = Item::factory()->create(['user_id' => $admin->getKey()]);
    $session = \app(InventorySessionService::class)->startDraft($admin, $otherStore);

    $this->be($limited, 'users')->putJson(\route('inventory-counts.drafts.rows.update', $session), [
        'item_id' => $item->getKey(),
        'quantity' => '2.500',
        'classification' => 'inventory_correction',
        'client_version' => 1,
    ])->assertForbidden();

    $this->be($limited, 'users')->post(\route('inventory-counts.drafts.close', $session))
        ->assertForbidden();
});
