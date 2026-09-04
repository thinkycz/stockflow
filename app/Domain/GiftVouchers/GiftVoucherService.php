<?php

declare(strict_types=1);

namespace App\Domain\GiftVouchers;

use App\Enums\GiftVoucherEventTypeEnum;
use App\Enums\GiftVoucherStatusEnum;
use App\Enums\OperationalActivityTypeEnum;
use App\Models\GiftVoucher;
use App\Models\GiftVoucherBatch;
use App\Models\GiftVoucherSetting;
use App\Models\Store;
use App\Models\User;
use App\Support\OperationalActivityService;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

class GiftVoucherService
{
    public const BUSINESS_TIMEZONE = 'Europe/Prague';

    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * Normalize text entry for exact matching and display.
     */
    public static function normalizeCode(string $code): string
    {
        $normalized = \mb_strtoupper((string) \preg_replace('/[^A-Z0-9]/i', '', $code));

        if (\mb_strlen($normalized) !== 16) {
            return $normalized;
        }

        return \implode('-', \mb_str_split($normalized, 4));
    }

    /**
     * Create an exact-search digest.
     */
    public static function hashCode(string $code): string
    {
        return \hash('sha256', self::normalizeCode($code));
    }

    /**
     * Generate a high-entropy human-readable code.
     */
    public static function generateCode(): string
    {
        $characters = '';
        $maximum = \mb_strlen(self::CODE_ALPHABET) - 1;

        for ($index = 0; $index < 16; ++$index) {
            $characters .= self::CODE_ALPHABET[\random_int(0, $maximum)];
        }

        return self::normalizeCode($characters);
    }

    /**
     * Issue a batch and its immutable initial audit events.
     */
    public function issue(
        User $admin,
        GiftVoucherSetting $setting,
        int $quantity,
        string $amount,
        string|null $expiresOn,
        string|null $brandLogoPath = null,
    ): GiftVoucherBatch {
        if (!$admin->isAdmin() || $setting->getUserId() !== $admin->getKey()) {
            \abort(403);
        }

        return DB::transaction(function () use ($admin, $setting, $quantity, $amount, $expiresOn, $brandLogoPath): GiftVoucherBatch {
            $batch = GiftVoucherBatch::query()->create([
                'user_id' => $admin->getKey(),
                'created_by_user_id' => $admin->getKey(),
                'quantity' => $quantity,
                'amount' => $amount,
                'expires_at' => $expiresOn === null
                    ? null
                    : CarbonImmutable::parse($expiresOn, self::BUSINESS_TIMEZONE)->endOfDay()->utc(),
                'brand_name' => $setting->getPublicName(),
                'brand_message' => $setting->getMessage(),
                'brand_logo_path' => $brandLogoPath,
                'brand_logo_mime' => $setting->getLogoMime(),
            ]);

            for ($position = 0; $position < $quantity; ++$position) {
                $voucher = $this->createUniqueVoucher($batch);
                $voucher->giftVoucherEvents()->create([
                    'actor_user_id' => $admin->getKey(),
                    'store_id' => null,
                    'type' => GiftVoucherEventTypeEnum::Issued->value,
                    'reason' => null,
                ]);
            }

            OperationalActivityService::dispatchToCompany(
                OperationalActivityTypeEnum::GIFT_VOUCHER_BATCH_ISSUED,
                $admin,
                CarbonImmutable::now('UTC')->toIso8601String(),
                Resolver::resolveUrlGenerator()->route('gift-vouchers.index'),
                [
                    'Slack voucher batch' => '#' . $batch->getKey(),
                    'Slack voucher quantity' => (string) $batch->getQuantity(),
                    'Slack voucher amount' => $this->formatCurrency($batch->getAmount()),
                    'Slack voucher total value' => $this->formatCurrency($batch->getAmount() * $batch->getQuantity()),
                    'Slack voucher expiration' => $batch->getExpiresAt()?->setTimezone(self::BUSINESS_TIMEZONE)->format('j. n. Y') ?? 'Bez expirace',
                ],
            );

            return $batch;
        });
    }

    /**
     * Find a voucher inside the company by exact normalized code.
     */
    public function findByCode(User $actor, string $code): GiftVoucher|null
    {
        $query = GiftVoucher::query()->with('giftVoucherBatch');
        GiftVoucher::scopeForUser($query, $actor->resolveScopeUser());

        return $query->where('code_hash', self::hashCode($code))->first();
    }

    /**
     * Redeem one active voucher at an authorized retail store.
     */
    public function redeem(User $actor, Store $store, GiftVoucher $voucher): GiftVoucher
    {
        return DB::transaction(function () use ($actor, $store, $voucher): GiftVoucher {
            $store = Typer::assertInstance(
                Store::query()->whereKey($store->getKey())->lockForUpdate()->firstOrFail(),
                Store::class,
            );
            $locked = GiftVoucher::query()->with('giftVoucherBatch')->lockForUpdate()->findOrFail($voucher->getKey());
            $owner = $actor->resolveScopeUser();

            if (
                $locked->getUserId() !== $owner->getKey() ||
                $store->getUserId() !== $owner->getKey() ||
                $store->isWarehouse() ||
                !$store->isActive() ||
                (!$actor->isAdmin() && $actor->getAssignedStoreId() !== $store->getKey())
            ) {
                \abort(403);
            }

            if ($locked->getEffectiveStatus() !== GiftVoucherStatusEnum::Active) {
                $this->fail('code', Typer::assertString(\__('This gift voucher cannot be redeemed.')));
            }

            $locked->setAttribute('status', GiftVoucherStatusEnum::Redeemed->value);
            $locked->setAttribute('redeemed_at', CarbonImmutable::now());
            $locked->setAttribute('redeemed_store_id', $store->getKey());
            $locked->setAttribute('redeemed_by_user_id', $actor->getKey());
            $locked->save();
            $locked->giftVoucherEvents()->create([
                'actor_user_id' => $actor->getKey(),
                'store_id' => $store->getKey(),
                'type' => GiftVoucherEventTypeEnum::Redeemed->value,
                'reason' => null,
            ]);

            OperationalActivityService::dispatch(
                OperationalActivityTypeEnum::GIFT_VOUCHER_REDEEMED,
                $actor,
                Typer::assertNotNull($locked->getRedeemedAt())->toIso8601String(),
                Resolver::resolveUrlGenerator()->route('gift-vouchers.index'),
                [['store' => $store, 'perspective' => null]],
                [
                    'Slack voucher' => '#' . $locked->getKey(),
                    'Slack voucher amount' => $this->formatCurrency($locked->getGiftVoucherBatch()->getAmount()),
                ],
            );

            return $locked;
        });
    }

    /**
     * Irreversibly void an active voucher.
     */
    public function void(User $admin, GiftVoucher $voucher, string $reason): GiftVoucher
    {
        return $this->adminTransition($admin, $voucher, GiftVoucherEventTypeEnum::Voided, $reason);
    }

    /**
     * Reverse an erroneous redemption while preserving its audit history.
     */
    public function reverseRedemption(User $admin, GiftVoucher $voucher, string $reason): GiftVoucher
    {
        return $this->adminTransition($admin, $voucher, GiftVoucherEventTypeEnum::RedemptionReversed, $reason);
    }

    /**
     * Create one voucher and retry an extremely unlikely digest collision.
     */
    private function createUniqueVoucher(GiftVoucherBatch $batch): GiftVoucher
    {
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $code = self::generateCode();

            try {
                return GiftVoucher::query()->create([
                    'gift_voucher_batch_id' => $batch->getKey(),
                    'user_id' => $batch->getUserId(),
                    'code' => $code,
                    'code_hash' => self::hashCode($code),
                    'status' => GiftVoucherStatusEnum::Active->value,
                    'redeemed_at' => null,
                    'redeemed_store_id' => null,
                    'redeemed_by_user_id' => null,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Generate a fresh code and retry within the surrounding transaction.
            }
        }

        throw new RuntimeException('A unique gift voucher code could not be generated.');
    }

    /**
     * Apply one audited admin-only state transition under a row lock.
     */
    private function adminTransition(
        User $admin,
        GiftVoucher $voucher,
        GiftVoucherEventTypeEnum $event,
        string $reason,
    ): GiftVoucher {
        return DB::transaction(function () use ($admin, $voucher, $event, $reason): GiftVoucher {
            $locked = GiftVoucher::query()->with('giftVoucherBatch')->lockForUpdate()->findOrFail($voucher->getKey());

            if (!$admin->isAdmin() || $locked->getUserId() !== $admin->getKey()) {
                \abort(403);
            }

            $expected = $event === GiftVoucherEventTypeEnum::Voided
                ? GiftVoucherStatusEnum::Active
                : GiftVoucherStatusEnum::Redeemed;

            if ($expected !== $locked->getEffectiveStatus()) {
                $this->fail('voucher', Typer::assertString(\__('This gift voucher state cannot be changed.')));
            }

            if ($event === GiftVoucherEventTypeEnum::Voided) {
                $eventStoreId = null;
                $locked->setAttribute('status', GiftVoucherStatusEnum::Voided->value);
            } else {
                $eventStoreId = $locked->getRedeemedStoreId();
                $locked->setAttribute('status', GiftVoucherStatusEnum::Active->value);
                $locked->setAttribute('redeemed_at', null);
                $locked->setAttribute('redeemed_store_id', null);
                $locked->setAttribute('redeemed_by_user_id', null);
            }

            $locked->save();
            $locked->giftVoucherEvents()->create([
                'actor_user_id' => $admin->getKey(),
                'store_id' => $eventStoreId,
                'type' => $event->value,
                'reason' => $reason,
            ]);

            $facts = [
                'Slack voucher' => '#' . $locked->getKey(),
                'Slack voucher amount' => $this->formatCurrency($locked->getGiftVoucherBatch()->getAmount()),
            ];
            if ($event === GiftVoucherEventTypeEnum::Voided) {
                OperationalActivityService::dispatchToCompany(
                    OperationalActivityTypeEnum::GIFT_VOUCHER_VOIDED,
                    $admin,
                    CarbonImmutable::now('UTC')->toIso8601String(),
                    Resolver::resolveUrlGenerator()->route('gift-vouchers.index'),
                    $facts,
                );
            } else {
                $eventStore = Typer::assertInstance(Store::query()->whereKey($eventStoreId)->firstOrFail(), Store::class);
                OperationalActivityService::dispatch(
                    OperationalActivityTypeEnum::GIFT_VOUCHER_REDEMPTION_REVERSED,
                    $admin,
                    CarbonImmutable::now('UTC')->toIso8601String(),
                    Resolver::resolveUrlGenerator()->route('gift-vouchers.index'),
                    [['store' => $eventStore, 'perspective' => null]],
                    $facts,
                );
            }

            return $locked;
        });
    }

    /**
     * Format one CZK voucher value for Slack.
     */
    private function formatCurrency(float $amount): string
    {
        return \number_format($amount, 2, ',', ' ') . ' Kč';
    }

    /**
     * Throw a repository-standard validation error.
     */
    private function fail(string $key, string $message): never
    {
        Thrower::default()->message($key, $message)->throw();
    }
}
