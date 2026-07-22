<?php

declare(strict_types=1);

use App\Enums\StoreStatusEnum;
use App\Models\Store;

\test('store create form is reachable', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();

    $this->be($user, 'users')->get('/stores/create', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'stores/Create');
});

\test('user can create a new store', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();

    $response = $this->be($user, 'users')
        ->withSession(['_token' => 'test'])
        ->withHeaders(['X-CSRF-TOKEN' => 'test'])
        ->post('/stores', [
            'name' => 'My Store',
            'address' => '123 Main St',
            'status' => StoreStatusEnum::ACTIVE->value,
            'notes' => null,
            'slack_channel' => '  #prodejna-praha  ',
            'is_warehouse' => false,
        ], $this->inertiaHeaders());

    $response->assertRedirect();
    $store = Store::query()->where('name', 'My Store')->first();
    \expect($store)->not->toBeNull();
    \expect($store->getUserId())->toBe($user->getKey());
    \expect($store->getSlackChannel())->toBe('#prodejna-praha');
    \assertInertiaFlash($response, 'success', \__('Store created.'));
});

\test('store create accepts an empty Slack channel and rejects an oversized one', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();

    $this->be($user, 'users')->post('/stores', [
        'name' => 'Without Slack',
        'status' => StoreStatusEnum::ACTIVE->value,
        'slack_channel' => '   ',
    ])->assertRedirect();

    $store = Store::query()->where('name', 'Without Slack')->firstOrFail();
    \expect($store->getSlackChannel())->toBeNull();

    $this->be($user, 'users')->post('/stores', [
        'name' => 'Invalid Slack',
        'status' => StoreStatusEnum::ACTIVE->value,
        'slack_channel' => \str_repeat('x', 101),
    ], $this->inertiaHeaders())->assertStatus(422);
});

\test('user can create a second warehouse store', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();

    $this->be($user, 'users')
        ->withSession(['_token' => 'test'])
        ->withHeaders(['X-CSRF-TOKEN' => 'test'])
        ->post('/stores', [
            'name' => 'Aux Warehouse',
            'address' => null,
            'status' => StoreStatusEnum::ACTIVE->value,
            'notes' => null,
            'is_warehouse' => true,
        ], ['Accept' => 'application/json'])
        ->assertRedirect();

    \expect(Store::query()
        ->where('user_id', $user->getKey())
        ->where('is_warehouse', true)
        ->count())->toBe(2);
});

\test('store create validates required name', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();

    $this->be($user, 'users')
        ->withSession(['_token' => 'test'])
        ->withHeaders(['X-CSRF-TOKEN' => 'test'])
        ->post('/stores', [
            'name' => '',
            'status' => StoreStatusEnum::ACTIVE->value,
        ], $this->inertiaHeaders())->assertStatus(422);
});
