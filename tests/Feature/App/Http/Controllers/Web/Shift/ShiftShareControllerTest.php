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

\test('limited user creates and reuses a public link only for the assigned store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
    ]);
    $otherStore = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
    ]);
    $limited = UserFactory::new()->limited($store)->createOne();

    $firstResponse = $this->be($limited, 'users')
        ->postJson('/shifts/share?store_id=' . $otherStore->getKey())
        ->assertOk();
    $firstToken = $store->refresh()->getShiftShareToken();
    $secondResponse = $this->be($limited, 'users')
        ->postJson('/shifts/share')
        ->assertOk();

    \expect($firstToken)->not->toBeNull()
        ->and($firstResponse->json('url'))->toBe(Resolver::resolveUrlGenerator()->to('public/shifts/' . $firstToken))
        ->and($secondResponse->json('url'))->toBe($firstResponse->json('url'))
        ->and($store->refresh()->getShiftShareToken())->toBe($firstToken)
        ->and($otherStore->refresh()->getShiftShareToken())->toBeNull();
});
