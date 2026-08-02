<?php

declare(strict_types=1);

use App\Enums\GiftVoucherStatusEnum;
use App\Models\GiftVoucher;
use App\Models\GiftVoucherSetting;
use App\Models\Store;
use App\Services\GiftVoucherService;
use Thinkycz\LaravelCore\Support\Typer;

\test('administrator voids and reverses vouchers with audited reasons', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $setting = GiftVoucherSetting::factory()->create(['user_id' => $admin->getKey()]);
    $batch = (new GiftVoucherService())->issue($admin, $setting, 2, '100.00', null);
    $vouchers = $batch->giftVouchers()->get();
    $voided = Typer::assertInstance($vouchers->first(), GiftVoucher::class);
    $redeemed = Typer::assertInstance($vouchers->last(), GiftVoucher::class);

    $this->be($admin, 'users')->post('/gift-vouchers/' . $voided->getKey() . '/void', [
        'reason' => 'Poukaz byl ztracen.',
    ])->assertRedirect();
    \expect($voided->refresh()->getStoredStatus())->toBe(GiftVoucherStatusEnum::Voided);

    (new GiftVoucherService())->redeem($admin, $store, $redeemed);
    $this->be($admin, 'users')->post('/gift-vouchers/' . $redeemed->getKey() . '/reverse-redemption', [
        'reason' => 'Chyba obsluhy.',
    ])->assertRedirect();
    \expect($redeemed->refresh()->getStoredStatus())->toBe(GiftVoucherStatusEnum::Active)
        ->and($redeemed->giftVoucherEvents()->count())->toBe(3);
});
