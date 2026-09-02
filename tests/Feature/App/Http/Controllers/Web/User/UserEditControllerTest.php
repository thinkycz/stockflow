<?php

declare(strict_types=1);

use App\Enums\LimitedUserSectionEnum;
use App\Models\Store;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('admin sees enabled sections when editing a limited user', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne([
        'disabled_sections' => [LimitedUserSectionEnum::SHIFTS->value],
    ]), User::class);

    $this->actingAs($admin)
        ->get(\route('users.edit', $limited))
        ->assertOk()
        ->assertInertia(static fn($page) => $page
            ->component('users/Edit')
            ->where('user.enabled_sections', \array_values(\array_filter(
                LimitedUserSectionEnum::values(),
                static fn(string $section): bool => $section !== LimitedUserSectionEnum::SHIFTS->value,
            )))
            ->where('section_options', LimitedUserSectionEnum::values()));
});

\test('admin can update a limited user', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $storeA = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $storeB = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($storeA)->createOne(), User::class);

    $response = $this->actingAs($admin)->put(\route('users.update', $limited), [
        'email' => 'renamed@example.com',
        'assigned_store_id' => $storeB->getKey(),
        'enabled_sections' => [
            LimitedUserSectionEnum::INCOMING->value,
            LimitedUserSectionEnum::RECIPES->value,
        ],
    ]);

    $response->assertRedirect(\route('users.index'));

    $limited->refresh();
    \expect($limited->getEmail())->toBe('renamed@example.com');
    \expect($limited->getAssignedStoreId())->toBe($storeB->getKey());
    \expect($limited->getEnabledSectionValues())->toBe([
        LimitedUserSectionEnum::INCOMING->value,
        LimitedUserSectionEnum::RECIPES->value,
    ]);
});

\test('admin can disable and re-enable every section for a limited user', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);

    $payload = [
        'email' => $limited->getEmail(),
        'assigned_store_id' => $store->getKey(),
    ];

    $this->actingAs($admin)
        ->put(\route('users.update', $limited), [...$payload, 'enabled_sections' => []])
        ->assertRedirect(\route('users.index'));

    \expect($limited->refresh()->getEnabledSectionValues())->toBe([]);
    \expect($limited->getDisabledSections())->toHaveCount(\count(LimitedUserSectionEnum::cases()));

    $this->actingAs($admin)
        ->put(\route('users.update', $limited), [
            ...$payload,
            'enabled_sections' => LimitedUserSectionEnum::values(),
        ])
        ->assertRedirect(\route('users.index'));

    \expect($limited->refresh()->getEnabledSectionValues())->toBe(LimitedUserSectionEnum::values());
    \expect($limited->getDisabledSections())->toBe([]);
});

\test('enabled sections reject unknown and duplicate values', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);

    $this->actingAs($admin)
        ->withHeaders($this->inertiaHeaders())
        ->put(\route('users.update', $limited), [
            'email' => $limited->getEmail(),
            'assigned_store_id' => $store->getKey(),
            'enabled_sections' => ['unknown', 'unknown'],
        ])
        ->assertSessionHasErrors(['enabled_sections.0', 'enabled_sections.1']);

    \expect($limited->refresh()->getDisabledSections())->toBe([]);
});

\test('admin cannot transfer a limited user to a foreign store', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $otherAdmin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $storeA = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $foreign = Store::factory()->create(['user_id' => $otherAdmin->getKey(), 'is_warehouse' => false]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($storeA)->createOne(), User::class);

    $this->actingAs($admin)
        ->withHeaders($this->inertiaHeaders())
        ->put(\route('users.update', $limited), [
            'email' => $limited->getEmail(),
            'assigned_store_id' => $foreign->getKey(),
            'enabled_sections' => LimitedUserSectionEnum::values(),
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['assigned_store_id']);
    $limited->refresh();
    \expect($limited->getAssignedStoreId())->toBe($storeA->getKey());
});

\test('admin can update their own email and password without losing admin role', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);

    $response = $this->actingAs($admin)->put(\route('users.update', $admin), [
        'email' => 'new-admin@example.com',
        'password' => 'new-secret-9',
        'password_confirmation' => 'new-secret-9',
    ]);

    $response->assertRedirect(\route('users.index'));

    $admin->refresh();
    \expect($admin->getEmail())->toBe('new-admin@example.com');
    \expect($admin->isAdmin())->toBeTrue();
    \expect($admin->getParentUserId())->toBeNull();
    \expect($admin->getAssignedStoreId())->toBeNull();
});

\test('admin cannot edit a user that is not in their tree', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $otherAdmin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $otherAdmin->getKey(), 'is_warehouse' => false]);
    $foreign = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);

    $response = $this->actingAs($admin)->get(\route('users.edit', $foreign));

    $response->assertForbidden();
});

\test('user editing exposes and accepts only active retail stores', function (): void {
    [$admin, $warehouse] = \createIsolatedUserWithWarehouse();
    $active = Store::factory()->create(['user_id' => $admin->getKey(), 'name' => 'Active']);
    $inactive = Store::factory()->inactive()->create(['user_id' => $admin->getKey(), 'name' => 'Inactive']);
    $limited = Typer::assertInstance(UserFactory::new()->limited($active)->createOne(), User::class);

    $this->actingAs($admin)
        ->get(\route('users.edit', $limited), $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'props.stores')
        ->assertJsonPath('props.stores.0.id', $active->getKey());

    foreach ([$warehouse, $inactive] as $store) {
        $this->actingAs($admin)
            ->put(\route('users.update', $limited), [
                'email' => $limited->getEmail(),
                'assigned_store_id' => $store->getKey(),
                'enabled_sections' => LimitedUserSectionEnum::values(),
            ])
            ->assertNotFound();

        \expect($limited->refresh()->getAssignedStoreId())->toBe($active->getKey());
    }
});
