<?php

declare(strict_types=1);

use App\Models\GiftVoucher;
use App\Models\GiftVoucherSetting;
use App\Services\GiftVoucherService;
use Thinkycz\LaravelCore\Support\Typer;

\test('voucher lookup is exact and isolated by company', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    [$other] = \createIsolatedUserWithWarehouse();
    $setting = GiftVoucherSetting::factory()->create(['user_id' => $admin->getKey()]);
    $voucher = Typer::assertInstance(
        (new GiftVoucherService())->issue($admin, $setting, 1, '300.00', null)->giftVouchers()->first(),
        GiftVoucher::class,
    );

    $this->be($other, 'users')->post('/gift-vouchers/lookup', [
        'code' => $voucher->getCode(),
    ], $this->inertiaHeaders())->assertRedirect()->assertSessionHasErrors('code');

    $this->be($admin, 'users')->post('/gift-vouchers/lookup', [
        'code' => \mb_strtolower(\str_replace('-', ' ', $voucher->getCode())),
    ])->assertRedirect('/gift-vouchers/redeem');

    $this->be($admin, 'users')->get('/gift-vouchers/redeem', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.lookup.voucher_id', $voucher->getKey());
});
