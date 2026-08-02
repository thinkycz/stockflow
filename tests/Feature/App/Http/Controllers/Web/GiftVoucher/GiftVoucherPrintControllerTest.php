<?php

declare(strict_types=1);

use App\Models\GiftVoucher;
use App\Models\GiftVoucherSetting;
use App\Services\GiftVoucherService;
use Thinkycz\LaravelCore\Support\Typer;

\test('administrator prints active vouchers in explicit three-up sheets', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $setting = GiftVoucherSetting::factory()->create(['user_id' => $admin->getKey(), 'public_name' => 'Coffee Lab']);
    $batch = (new GiftVoucherService())->issue($admin, $setting, 4, '750.00', null);

    $this->be($admin, 'users')->get('/gift-voucher-batches/' . $batch->getKey() . '/print', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'gift-vouchers/Print')
        ->assertJsonCount(2, 'props.sheets')
        ->assertJsonCount(3, 'props.sheets.0')
        ->assertJsonCount(1, 'props.sheets.1');

    $voucher = Typer::assertInstance($batch->giftVouchers()->first(), GiftVoucher::class);
    $this->be($admin, 'users')->get('/gift-vouchers/' . $voucher->getKey() . '/print', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'props.sheets.0');
});
