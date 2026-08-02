<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Database\Factories\GiftVoucherBatchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class GiftVoucherBatch extends BaseModel
{
    use BelongsToUser;
    /** @use HasFactory<GiftVoucherBatchFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'gift_voucher_batches';

    /**
     * Scope batches by their snapshotted public brand name.
     *
     * @param Builder<GiftVoucherBatch> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where('brand_name', 'like', '%' . $search . '%');
    }

    /**
     * Restrict a query to the columns used by the application.
     *
     * @param Builder<GiftVoucherBatch> $query
     *
     * @return Builder<GiftVoucherBatch>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select([
            'id', 'user_id', 'created_by_user_id', 'quantity', 'amount', 'expires_at',
            'brand_name', 'brand_message', 'brand_logo_path', 'brand_logo_mime', 'created_at', 'updated_at',
        ]);
    }

    /**
     * Vouchers issued in this batch.
     *
     * @return HasMany<GiftVoucher, $this>
     */
    public function giftVouchers(): HasMany
    {
        return $this->hasMany(GiftVoucher::class);
    }

    /**
     * Loaded vouchers issued in this batch.
     *
     * @return Collection<array-key, GiftVoucher>
     */
    public function getGiftVouchers(): Collection
    {
        return $this->assertRelationshipCollection('giftVouchers', GiftVoucher::class);
    }

    /**
     * Company owner id.
     */
    public function getUserId(): int
    {
        return $this->assertInt('user_id');
    }

    /**
     * Issued voucher count.
     */
    public function getQuantity(): int
    {
        return $this->assertInt('quantity');
    }

    /**
     * Nominal value of every voucher.
     */
    public function getAmount(): float
    {
        return (float) Typer::assertString($this->getAttribute('amount'));
    }

    /**
     * Optional UTC expiration timestamp.
     */
    public function getExpiresAt(): Carbon|null
    {
        return $this->assertNullableCarbon('expires_at');
    }

    /**
     * Snapshotted public brand name.
     */
    public function getBrandName(): string
    {
        return $this->assertString('brand_name');
    }

    /**
     * Snapshotted optional message.
     */
    public function getBrandMessage(): string|null
    {
        return $this->assertNullableString('brand_message');
    }

    /**
     * Snapshotted private logo path.
     */
    public function getBrandLogoPath(): string|null
    {
        return $this->assertNullableString('brand_logo_path');
    }

    /**
     * Snapshotted logo MIME type.
     */
    public function getBrandLogoMime(): string|null
    {
        return $this->assertNullableString('brand_logo_mime');
    }

    /**
     * Model casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'expires_at' => 'datetime'];
    }
}
