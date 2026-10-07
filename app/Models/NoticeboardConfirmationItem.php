<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NoticeboardCardColorEnum;
use App\Enums\NoticeboardCardLabelEnum;
use App\Enums\NoticeboardCardSizeEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class NoticeboardConfirmationItem extends BaseModel
{
    /**
     * @param Builder<NoticeboardConfirmationItem> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where('body_html', 'like', '%' . $search . '%');
    }

    /**
     * @param Builder<NoticeboardConfirmationItem> $query
     *
     * @return Builder<NoticeboardConfirmationItem>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'confirmation_id', 'noticeboard_card_id', 'card_version', 'body_html', 'label', 'color', 'size', 'image_path', 'image_mime', 'position', 'created_at', 'updated_at']);
    }

    /**
     * @return BelongsTo<NoticeboardConfirmation, $this>
     */
    public function confirmation(): BelongsTo
    {
        return $this->belongsTo(NoticeboardConfirmation::class, 'confirmation_id');
    }

    /**
     * Loaded or queried owning confirmation.
     */
    public function getConfirmation(): NoticeboardConfirmation
    {
        return $this->relationLoaded('confirmation')
            ? $this->assertRelationship('confirmation', NoticeboardConfirmation::class)
            : $this->confirmation()->firstOrFail();
    }

    /**
     * Source card, if it still exists.
     */
    public function getNoticeboardCardId(): int|null { return $this->assertNullableInt('noticeboard_card_id'); }

    /**
     * Source card version.
     */
    public function getCardVersion(): int { return $this->assertInt('card_version'); }

    /**
     * Sanitized content at arrival.
     */
    public function getBodyHtml(): string { return $this->assertString('body_html'); }

    /**
     * Card label at arrival.
     */
    public function getLabel(): NoticeboardCardLabelEnum { return Typer::assertInstance($this->getAttribute('label'), NoticeboardCardLabelEnum::class); }

    /**
     * Card color at arrival.
     */
    public function getColor(): NoticeboardCardColorEnum { return Typer::assertInstance($this->getAttribute('color'), NoticeboardCardColorEnum::class); }

    /**
     * Card size at arrival.
     */
    public function getSize(): NoticeboardCardSizeEnum { return Typer::assertInstance($this->getAttribute('size'), NoticeboardCardSizeEnum::class); }

    /**
     * Private immutable image path.
     */
    public function getImagePath(): string|null { return $this->assertNullableString('image_path'); }

    /**
     * Snapshotted image MIME type.
     */
    public function getImageMime(): string|null { return $this->assertNullableString('image_mime'); }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['label' => NoticeboardCardLabelEnum::class, 'color' => NoticeboardCardColorEnum::class, 'size' => NoticeboardCardSizeEnum::class];
    }
}
