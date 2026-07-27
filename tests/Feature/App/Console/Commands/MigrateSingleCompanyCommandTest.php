<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\Store;
use Database\Factories\UserFactory;

\test('single company migration has an idempotent dry run and moves orphan data', function (): void {
    $admin = UserFactory::new()->admin()->createOne();
    $target = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $orphan = UserFactory::new()->createOne();
    $orphanStore = Store::factory()->create(['user_id' => $orphan->getKey()]);
    $item = Item::factory()->create(['user_id' => $orphan->getKey()]);

    $this->artisan('stockflow:migrate-single-company', ['--dry-run' => true])->assertSuccessful();
    \expect($orphan->fresh()?->getParentUserId())->toBeNull()
        ->and($item->fresh()?->getUserId())->toBe($orphan->getKey());

    $this->artisan('stockflow:migrate-single-company')->assertSuccessful();
    $this->artisan('stockflow:migrate-single-company')->assertSuccessful();

    \expect($orphan->fresh()?->getParentUserId())->toBe($admin->getKey())
        ->and($orphan->fresh()?->getAssignedStoreId())->toBe($target->getKey())
        ->and($item->fresh()?->getUserId())->toBe($admin->getKey())
        ->and($orphanStore->fresh()?->getUserId())->toBe($admin->getKey());
});
