<?php

declare(strict_types=1);

use App\Enums\GiftVoucherStatusEnum;
use App\Models\GiftVoucher;
use App\Models\GiftVoucherSetting;
use App\Models\Store;
use App\Services\GiftVoucherService;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('limited account redeems a lookup ticket once at its assigned store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $limited = UserFactory::new()->limited($store)->createOne();
    $setting = GiftVoucherSetting::factory()->create(['user_id' => $admin->getKey()]);
    $voucher = Typer::assertInstance(
        (new GiftVoucherService())->issue($admin, $setting, 1, '300.00', null)->giftVouchers()->first(),
        GiftVoucher::class,
    );

    $this->be($limited, 'users')->post('/gift-vouchers/lookup', ['code' => $voucher->getCode()]);
    $page = $this->be($limited, 'users')->get('/gift-vouchers/redeem', $this->inertiaHeaders())->assertOk();
    $ticket = $page->json('props.lookup.ticket');

    $this->be($limited, 'users')->post('/gift-vouchers/' . $voucher->getKey() . '/redeem', [
        'ticket' => $ticket,
    ])->assertRedirect('/gift-vouchers/redeem');

    \expect($voucher->refresh()->getStoredStatus())->toBe(GiftVoucherStatusEnum::Redeemed)
        ->and($voucher->getRedeemedStoreId())->toBe($store->getKey());

    $this->be($limited, 'users')->post('/gift-vouchers/' . $voucher->getKey() . '/redeem', [
        'ticket' => $ticket,
    ], $this->inertiaHeaders())->assertRedirect()->assertSessionHasErrors('ticket');
});
