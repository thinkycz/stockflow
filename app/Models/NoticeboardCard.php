<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NoticeboardCardColorEnum;
use App\Enums\NoticeboardCardLabelEnum;
use App\Models\Concerns\BelongsToUser;
use Database\Factories\NoticeboardCardFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class NoticeboardCard extends BaseModel
{
    use BelongsToUser;
    /** @use HasFactory<NoticeboardCardFactory> */
    use HasFactory;
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'noticeboard_cards';

    /**
     * Search cards by title or searchable body text.
     *
     * @param Builder<NoticeboardCard> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where(static function (Builder $query) use ($search): void {
            $query->where('title', 'like', '%' . $search . '%')
                ->orWhere('body_text', 'like', '%' . $search . '%');
        });
    }

    /**
     * Scope cards to one store.
     *
     * @param Builder<NoticeboardCard> $query
     */
    public static function scopeForStore(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    /**
     * Restrict the query to card list columns.
     *
     * @param Builder<NoticeboardCard> $query
     *
     * @return Builder<NoticeboardCard>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select([
            'id', 'user_id', 'store_id', 'created_by_user_id', 'updated_by_user_id',
            'title', 'body_html', 'body_text', 'label', 'color', 'image_path',
            'image_mime', 'expires_at', 'lock_version', 'created_at', 'updated_at', 'deleted_at',
        ]);
    }

    /**
     * Creator relationship.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Last editor relationship.
     *
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * Owning store relationship.
     *
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /**
     * Company owner id.
     */
    public function getUserId(): int
    {
        return $this->assertInt('user_id');
    }

    /**
     * Store id.
     */
    public function getStoreId(): int
    {
        return $this->assertInt('store_id');
    }

    /**
     * Card title.
     */
    public function getTitle(): string
    {
        return $this->assertString('title');
    }

    /**
     * Sanitized rich-text body.
     */
    public function getBodyHtml(): string
    {
        return $this->assertString('body_html');
    }

    /**
     * Card label.
     */
    public function getLabel(): NoticeboardCardLabelEnum
    {
        return Typer::assertInstance($this->getAttribute('label'), NoticeboardCardLabelEnum::class);
    }

    /**
     * Card color.
     */
    public function getColor(): NoticeboardCardColorEnum
    {
        return Typer::assertInstance($this->getAttribute('color'), NoticeboardCardColorEnum::class);
    }

    /**
     * Private image path.
     */
    public function getImagePath(): string|null
    {
        return $this->assertNullableString('image_path');
    }

    /**
     * Image MIME type.
     */
    public function getImageMime(): string|null
    {
        return $this->assertNullableString('image_mime');
    }

    /**
     * Expiration timestamp in UTC.
     */
    public function getExpiresAt(): Carbon|null
    {
        return $this->assertNullableCarbon('expires_at');
    }

    /**
     * Optimistic lock version.
     */
    public function getLockVersion(): int
    {
        return $this->assertInt('lock_version');
    }

    /**
     * Creator id.
     */
    public function getCreatedByUserId(): int|null
    {
        return $this->assertNullableInt('created_by_user_id');
    }

    /**
     * Last editor id.
     */
    public function getUpdatedByUserId(): int|null
    {
        return $this->assertNullableInt('updated_by_user_id');
    }

    /**
     * Soft-deletion timestamp.
     */
    public function getDeletedAt(): Carbon|null
    {
        return $this->assertNullableCarbon('deleted_at');
    }

    /**
     * Loaded creator when the account still exists.
     */
    public function getCreator(): User|null
    {
        return $this->assertNullableRelation('creator', User::class);
    }

    /**
     * Loaded last editor when the account still exists.
     */
    public function getUpdater(): User|null
    {
        return $this->assertNullableRelation('updater', User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'label' => NoticeboardCardLabelEnum::class,
            'color' => NoticeboardCardColorEnum::class,
            'expires_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
