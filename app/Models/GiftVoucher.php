<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GiftVoucherStatusEnum;
use App\Models\Concerns\BelongsToUser;
use Database\Factories\GiftVoucherFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class GiftVoucher extends BaseModel
{
    use BelongsToUser;
    /** @use HasFactory<GiftVoucherFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'gift_vouchers';

    /**
     * Apply the exact hashed-code search performed by the voucher service.
     *
     * @param Builder<GiftVoucher> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where('code_hash', $search);
    }

    /**
     * Restrict a query to the columns used by the application.
     *
     * @param Builder<GiftVoucher> $query
     *
     * @return Builder<GiftVoucher>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select([
            'id', 'gift_voucher_batch_id', 'user_id', 'code', 'code_hash', 'status',
            'redeemed_at', 'redeemed_store_id', 'redeemed_by_user_id', 'created_at', 'updated_at',
        ]);
    }

    /**
     * Issuing batch.
     *
     * @return BelongsTo<GiftVoucherBatch, $this>
     */
    public function giftVoucherBatch(): BelongsTo
    {
        return $this->belongsTo(GiftVoucherBatch::class);
    }

    /**
     * Immutable lifecycle events.
     *
     * @return HasMany<GiftVoucherEvent, $this>
     */
    public function giftVoucherEvents(): HasMany
    {
        return $this->hasMany(GiftVoucherEvent::class);
    }

    /**
     * Loaded immutable lifecycle events.
     *
     * @return Collection<array-key, GiftVoucherEvent>
     */
    public function getGiftVoucherEvents(): Collection
    {
        return $this->assertRelationshipCollection('giftVoucherEvents', GiftVoucherEvent::class);
    }

    /**
     * Loaded or queried issuing batch.
     */
    public function getGiftVoucherBatch(): GiftVoucherBatch
    {
        if ($this->relationLoaded('giftVoucherBatch')) {
            return Typer::assertInstance($this->assertRelationship('giftVoucherBatch', GiftVoucherBatch::class), GiftVoucherBatch::class);
        }

        return Typer::assertInstance($this->giftVoucherBatch()->firstOrFail(), GiftVoucherBatch::class);
    }

    /**
     * Company owner id.
     */
    public function getUserId(): int
    {
        return $this->assertInt('user_id');
    }

    /**
     * Decrypted human-readable code.
     */
    public function getCode(): string
    {
        return $this->assertString('code');
    }

    /**
     * Persisted lifecycle status.
     */
    public function getStoredStatus(): GiftVoucherStatusEnum
    {
        return GiftVoucherStatusEnum::from($this->assertString('status'));
    }

    /**
     * Status including derived expiration.
     */
    public function getEffectiveStatus(): GiftVoucherStatusEnum
    {
        $stored = $this->getStoredStatus();

        if ($stored === GiftVoucherStatusEnum::Active) {
            $expiresAt = $this->getGiftVoucherBatch()->getExpiresAt();

            if ($expiresAt !== null && $expiresAt->isPast()) {
                return GiftVoucherStatusEnum::Expired;
            }
        }

        return $stored;
    }

    /**
     * Redemption timestamp.
     */
    public function getRedeemedAt(): Carbon|null
    {
        return $this->assertNullableCarbon('redeemed_at');
    }

    /**
     * Redemption store id.
     */
    public function getRedeemedStoreId(): int|null
    {
        return $this->assertNullableInt('redeemed_store_id');
    }

    /**
     * Redeeming user id.
     */
    public function getRedeemedByUserId(): int|null
    {
        return $this->assertNullableInt('redeemed_by_user_id');
    }

    /**
     * Model casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['code' => 'encrypted', 'redeemed_at' => 'datetime'];
    }
}
