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
        ->assertOk()
        ->assertJsonPath('props.is_admin', false)
        ->assertJsonPath('props.batches', []);
});
