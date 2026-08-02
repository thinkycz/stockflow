<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Database\Factories\GiftVoucherSettingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Thinkycz\LaravelCore\Models\BaseModel;

class GiftVoucherSetting extends BaseModel
{
    use BelongsToUser;
    /** @use HasFactory<GiftVoucherSettingFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'gift_voucher_settings';

    /**
     * Scope settings by their public business name.
     *
     * @param Builder<GiftVoucherSetting> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where('public_name', 'like', '%' . $search . '%');
    }

    /**
     * Restrict a query to the columns used by the application.
     *
     * @param Builder<GiftVoucherSetting> $query
     *
     * @return Builder<GiftVoucherSetting>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select([
            'id', 'user_id', 'public_name', 'message', 'logo_path', 'logo_mime', 'created_at', 'updated_at',
        ]);
    }

    /**
     * Company owner id.
     */
    public function getUserId(): int
    {
        return $this->assertInt('user_id');
    }

    /**
     * Customer-facing company name.
     */
    public function getPublicName(): string
    {
        return $this->assertString('public_name');
    }

    /**
     * Optional customer-facing voucher message.
     */
    public function getMessage(): string|null
    {
        return $this->assertNullableString('message');
    }

    /**
     * Private current logo path.
     */
    public function getLogoPath(): string|null
    {
        return $this->assertNullableString('logo_path');
    }

    /**
     * Current logo MIME type.
     */
    public function getLogoMime(): string|null
    {
        return $this->assertNullableString('logo_mime');
    }

    /**
     * Model casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }
}
