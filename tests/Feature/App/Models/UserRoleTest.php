<?php

declare(strict_types=1);

use App\Models\Store;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('admin factory state sets is_admin to true', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);

    \expect($admin->isAdmin())->toBeTrue();
    \expect($admin->getParentUserId())->toBeNull();
    \expect($admin->getAssignedStoreId())->toBeNull();
});

\test('default factory state is a limited user with no parent and no store', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    \expect($user->isAdmin())->toBeFalse();
    \expect($user->getParentUserId())->toBeNull();
    \expect($user->getAssignedStoreId())->toBeNull();
});

\test('limited factory state pins the user to the supplied store and parent', function (): void {
    [$admin, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
    ]);

    $user = Typer::assertInstance(
        UserFactory::new()->limited($retail)->createOne(),
        User::class,
    );

    \expect($user->isAdmin())->toBeFalse();
    \expect($user->getParentUserId())->toBe($admin->getKey());
    \expect($user->getAssignedStoreId())->toBe($retail->getKey());
    \expect($user->getAssignedStore()?->getKey())->toBe($retail->getKey());

    // Warehouse created for the limited user is independent and not used here.
    \expect($warehouse)->not->toBeNull();
});

\test('User::scopeAdmin keeps only admin users', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $query = User::query();
    User::scopeAdmin($query);

    \expect($query->count())->toBe(1);
    \expect($query->first()->getKey())->toBe($admin->getKey());
});

\test('User::scopeLimited keeps only non-admin users', function (): void {
    Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $limited = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $query = User::query();
    User::scopeLimited($query);

    \expect($query->count())->toBe(1);
    \expect($query->first()->getKey())->toBe($limited->getKey());
});

\test('User::scopeForAdmin returns the admin and their subordinates', function (): void {
    [$admin, $warehouse] = \createIsolatedUserWithWarehouse();
    $admin->update(['is_admin' => true, 'parent_user_id' => null, 'assigned_store_id' => null]);

    $otherAdmin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $foreignLimited = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $own = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
    ]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($own)->createOne(), User::class);

    $query = User::query();
    User::scopeForAdmin($query, $admin);

    $ids = $query->pluck('id')->all();

    \expect($ids)->toContain($admin->getKey());
    \expect($ids)->toContain($limited->getKey());
    \expect($ids)->not->toContain($otherAdmin->getKey());
    \expect($ids)->not->toContain($foreignLimited->getKey());

    \expect($warehouse)->not->toBeNull();
});
