<?php

declare(strict_types=1);

use App\Models\Store;
use Database\Factories\UserFactory;

\test('gift voucher index exposes role appropriate content', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $limited = UserFactory::new()->limited($store)->createOne();

    $this->be($admin, 'users')->get('/gift-vouchers?store_id=' . $store->getKey(), $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'gift-vouchers/Index')
        ->assertJsonPath('props.is_admin', true)
        ->assertJsonPath('props.can_redeem', true);

    $this->be($limited, 'users')->get('/gift-vouchers', $this->inertiaHeaders())
        ->assertRedirect('/gift-vouchers/redeem');

    $this->be($limited, 'users')->get('/gift-vouchers/redeem', $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('component', 'gift-vouchers/Redeem')
        ->assertJsonPath('props.is_admin', false)->assertJsonPath('props.batches', []);
});

\test('voucher pages and legacy links resolve to dedicated workflows', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $this->be($admin, 'users');
    foreach (['/gift-voucher-batches/create' => 'Create', '/gift-voucher-settings' => 'Settings', '/gift-vouchers/redeem' => 'Redeem'] as $url => $page) {
        $this->get($url, $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'gift-vouchers/' . $page);
    }
    foreach (['issue' => '/gift-voucher-batches/create', 'settings' => '/gift-voucher-settings', 'redeem' => '/gift-vouchers/redeem', 'overview' => '/gift-vouchers'] as $tab => $url) {
        $this->get('/gift-vouchers?tab=' . $tab)->assertRedirect($url);
    }
    $this->get('/gift-vouchers?tab=overview&status=active&search=ABC')->assertRedirect('/gift-vouchers?status=active&search=ABC');
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $limited = UserFactory::new()->limited($store)->createOne();
    foreach (['/gift-voucher-batches/create', '/gift-voucher-settings'] as $url) {
        $this->be($limited, 'users')->get($url)->assertRedirect('/dashboard');
    }
});
