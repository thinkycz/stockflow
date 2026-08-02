<?php

declare(strict_types=1);

use App\Models\GiftVoucher;
use App\Models\GiftVoucherSetting;
use App\Models\Store;
use Database\Factories\UserFactory;

\test('only an administrator with branding can issue a voucher batch', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $limited = UserFactory::new()->limited($store)->createOne();
    GiftVoucherSetting::factory()->create(['user_id' => $admin->getKey()]);

    $this->be($limited, 'users')->post('/gift-voucher-batches', [
        'quantity' => 4, 'amount' => '750.00',
    ])->assertRedirect('/dashboard');

    $this->be($admin, 'users')->post('/gift-voucher-batches', [
        'quantity' => 4, 'amount' => '750.00', 'expires_on' => '2026-12-31',
    ])->assertRedirect();

    \expect(GiftVoucher::query()->where('user_id', $admin->getKey())->count())->toBe(4);
});
