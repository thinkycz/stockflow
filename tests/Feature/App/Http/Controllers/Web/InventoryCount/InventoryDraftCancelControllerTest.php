<?php

declare(strict_types=1);

use App\Domain\Inventory\InventorySessionService;
use App\Models\Store;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('inventory draft cancel controller releases the active draft slot', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $session = \app(InventorySessionService::class)->startDraft($user, $store);

    $this->be($user, 'users')->post(\route('inventory-counts.drafts.cancel', $session))
        ->assertRedirect(\route('inventory-counts.index'));

    \expect($session->fresh()->getStatus())->toBe('cancelled');
    \expect($session->fresh()->getAttribute('active_store_key'))->toBeNull();
});

\test('limited user can cancel a draft for their assigned store', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);
    $session = \app(InventorySessionService::class)->startDraft($limited, $store);

    $this->be($limited, 'users');

    $this->post(\route('inventory-counts.drafts.cancel', $session))
        ->assertRedirect(\route('inventory-counts.index'));

    \expect($session->fresh()?->getStatus())->toBe('cancelled');
});

\test('limited user cannot cancel a draft for another store', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $assignedStore = Store::factory()->create(['user_id' => $admin->getKey()]);
    $otherStore = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($assignedStore)->createOne(), User::class);
    $session = \app(InventorySessionService::class)->startDraft($admin, $otherStore);

    $this->be($limited, 'users');

    $this->post(\route('inventory-counts.drafts.cancel', $session))->assertForbidden();

    \expect($session->fresh()?->getStatus())->toBe('draft');
});
