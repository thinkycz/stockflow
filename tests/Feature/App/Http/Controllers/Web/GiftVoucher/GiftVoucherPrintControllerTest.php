<?php

declare(strict_types=1);

use App\Domain\GiftVouchers\GiftVoucherService;
use App\Enums\StoreStatusEnum;
use App\Models\GiftVoucher;
use App\Models\GiftVoucherSetting;
use App\Models\Store;
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

\test('print lists only the owners active retail branches with their addresses', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    [$other] = \createIsolatedUserWithWarehouse();
    $setting = GiftVoucherSetting::factory()->create(['user_id' => $admin->getKey()]);
    $batch = (new GiftVoucherService())->issue($admin, $setting, 1, '750.00', null);
    $branch = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'name' => 'Teacha Centrum',
        'address' => 'Testovací 12, Praha',
        'status' => StoreStatusEnum::ACTIVE,
        'is_warehouse' => false,
    ]);
    Store::factory()->create(['user_id' => $admin->getKey(), 'status' => StoreStatusEnum::INACTIVE, 'is_warehouse' => false]);
    Store::factory()->create(['user_id' => $other->getKey(), 'status' => StoreStatusEnum::ACTIVE, 'is_warehouse' => false]);

    $this->be($admin, 'users')->get('/gift-voucher-batches/' . $batch->getKey() . '/print', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'props.branches')
        ->assertJsonPath('props.branches.0.id', $branch->getKey())
        ->assertJsonPath('props.branches.0.name', 'Teacha Centrum')
        ->assertJsonPath('props.branches.0.address', 'Testovací 12, Praha');
});
