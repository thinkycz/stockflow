<?php

declare(strict_types=1);

use App\Models\Store;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Resolver;

\test('admin can create a persistent public shift calendar link for the active store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
    ]);

    $response = $this->be($admin, 'users')
        ->postJson('/shifts/share');

    $response->assertOk();
    $store->refresh();

    $token = $store->getShiftShareToken();

    \expect($token)->not->toBeNull();
    $response->assertJsonPath('url', Resolver::resolveUrlGenerator()->to('public/shifts/' . $token));
});

\test('share endpoint reuses the active store token', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
    ]);

    $firstUrl = $this->be($admin, 'users')->postJson('/shifts/share')->json('url');
    $firstToken = $store->refresh()->getShiftShareToken();
    $secondUrl = $this->be($admin, 'users')->postJson('/shifts/share')->json('url');

    \expect($firstToken)->not->toBeNull()
        ->and($store->refresh()->getShiftShareToken())->toBe($firstToken)
        ->and($secondUrl)->toBe($firstUrl);
});

\test('limited user cannot create a public shift calendar link', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
    ]);
    $limited = UserFactory::new()->limited($store)->createOne();

    $this->be($limited, 'users')
        ->postJson('/shifts/share')
        ->assertRedirect('/dashboard');

    \expect($store->refresh()->getShiftShareToken())->toBeNull();
});
