<?php

declare(strict_types=1);

use App\Enums\GiftVoucherEventTypeEnum;
use App\Enums\GiftVoucherStatusEnum;
use App\Models\GiftVoucher;
use App\Models\GiftVoucherSetting;
use App\Models\Store;
use App\Services\GiftVoucherService;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Validation\ValidationException;
use Thinkycz\LaravelCore\Support\Typer;

\test('administrator issues a secure batch with normalized searchable codes', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $setting = GiftVoucherSetting::factory()->create([
        'user_id' => $admin->getKey(),
        'public_name' => 'Kavárna Test',
        'message' => 'Děkujeme.',
    ]);

    $batch = (new GiftVoucherService())->issue($admin, $setting, 10, '500.00', '2026-12-31');

    $vouchers = $batch->giftVouchers()->orderBy('id')->get();

    \expect($batch->getQuantity())->toBe(10)
        ->and($batch->getAmount())->toBe(500.0)
        ->and($batch->getExpiresAt()?->toDateTimeString())->toBe('2026-12-31 22:59:59')
        ->and($batch->getBrandName())->toBe('Kavárna Test')
        ->and($vouchers)->toHaveCount(10)
        ->and($vouchers->pluck('code_hash')->unique())->toHaveCount(10);

    $voucher = Typer::assertInstance($vouchers->first(), GiftVoucher::class);
    \expect($voucher->getCode())->toMatch('/^[A-HJ-NP-Z2-9]{4}(?:-[A-HJ-NP-Z2-9]{4}){3}$/')
        ->and($voucher->getRawOriginal('code'))->not->toBe($voucher->getCode())
        ->and((new GiftVoucherService())->findByCode($admin, \mb_strtolower(\str_replace('-', ' ', $voucher->getCode())))?->getKey())
        ->toBe($voucher->getKey())
        ->and($voucher->giftVoucherEvents()->where('type', GiftVoucherEventTypeEnum::Issued->value)->count())
        ->toBe(1);
});

\test('supported batch sizes are issued atomically', function (int $quantity): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $setting = GiftVoucherSetting::factory()->create(['user_id' => $admin->getKey()]);

    $batch = (new GiftVoucherService())->issue($admin, $setting, $quantity, '0.01', null);

    \expect($batch->getQuantity())->toBe($quantity)
        ->and($batch->giftVouchers()->count())->toBe($quantity)
        ->and($batch->giftVouchers()->distinct()->count('code_hash'))->toBe($quantity)
        ->and($batch->giftVouchers()->where('status', GiftVoucherStatusEnum::Active->value)->count())->toBe($quantity);
})->with([1, 10, 20, 100]);

\test('voucher lifecycle is atomic audited and expiration aware', function (): void {
    CarbonImmutable::setTestNow('2026-08-02 10:00:00 Europe/Prague');
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $limited = UserFactory::new()->limited($store)->createOne();
    $setting = GiftVoucherSetting::factory()->create(['user_id' => $admin->getKey()]);
    $voucher = Typer::assertInstance(
        (new GiftVoucherService())->issue($admin, $setting, 1, '250.00', '2026-08-02')->giftVouchers()->first(),
        GiftVoucher::class,
    );
    $service = new GiftVoucherService();

    $service->redeem($limited, $store, $voucher);

    \expect($voucher->refresh()->getStoredStatus())->toBe(GiftVoucherStatusEnum::Redeemed)
        ->and($voucher->getEffectiveStatus())->toBe(GiftVoucherStatusEnum::Redeemed)
        ->and($voucher->getRedeemedStoreId())->toBe($store->getKey())
        ->and($voucher->giftVoucherEvents()->where('type', GiftVoucherEventTypeEnum::Redeemed->value)->count())
        ->toBe(1);

    \expect(fn(): GiftVoucher => $service->redeem($limited, $store, $voucher))->toThrow(ValidationException::class);

    $service->reverseRedemption($admin, $voucher, 'Obsluha použila špatný kód.');
    \expect($voucher->refresh()->getStoredStatus())->toBe(GiftVoucherStatusEnum::Active)
        ->and($voucher->getRedeemedAt())->toBeNull()
        ->and($voucher->giftVoucherEvents()->where('type', GiftVoucherEventTypeEnum::RedemptionReversed->value)->count())
        ->toBe(1);

    CarbonImmutable::setTestNow('2026-08-03 00:00:00 Europe/Prague');
    \expect($voucher->refresh()->getEffectiveStatus())->toBe(GiftVoucherStatusEnum::Expired)
        ->and(fn(): GiftVoucher => $service->redeem($limited, $store, $voucher))->toThrow(ValidationException::class);
});

\test('only an owning administrator can void an active retail voucher', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $setting = GiftVoucherSetting::factory()->create(['user_id' => $admin->getKey()]);
    $voucher = Typer::assertInstance(
        (new GiftVoucherService())->issue($admin, $setting, 1, '100.00', null)->giftVouchers()->first(),
        GiftVoucher::class,
    );
    $service = new GiftVoucherService();

    $service->void($admin, $voucher, 'Poukaz byl ztracen.');

    \expect($voucher->refresh()->getStoredStatus())->toBe(GiftVoucherStatusEnum::Voided)
        ->and($voucher->giftVoucherEvents()->where('type', GiftVoucherEventTypeEnum::Voided->value)->count())
        ->toBe(1)
        ->and(fn(): GiftVoucher => $service->void($admin, $voucher, 'Znovu.'))->toThrow(ValidationException::class);
});
