<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GiftVoucherEventTypeEnum;
use Database\Factories\GiftVoucherEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use LogicException;
use Thinkycz\LaravelCore\Models\BaseModel;

class GiftVoucherEvent extends BaseModel
{
    /** @use HasFactory<GiftVoucherEventFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'gift_voucher_events';

    /**
     * Scope audit events by their optional correction reason.
     *
     * @param Builder<GiftVoucherEvent> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where('reason', 'like', '%' . $search . '%');
    }

    /**
     * Restrict a query to the columns used by the application.
     *
     * @param Builder<GiftVoucherEvent> $query
     *
     * @return Builder<GiftVoucherEvent>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select([
            'id', 'gift_voucher_id', 'actor_user_id', 'store_id', 'type', 'reason', 'created_at', 'updated_at',
        ]);
    }

    /**
     * Reject changes to persisted audit records.
     *
     * @param array<string, mixed> $options
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Gift voucher events are immutable.');
        }

        return parent::save($options);
    }

    /**
     * Reject deletion of immutable audit records.
     */
    public function delete(): bool
    {
        throw new LogicException('Gift voucher events are immutable.');
    }

    /**
     * Event type.
     */
    public function getType(): GiftVoucherEventTypeEnum
    {
        return GiftVoucherEventTypeEnum::from($this->assertString('type'));
    }

    /**
     * Optional reason.
     */
    public function getReason(): string|null
    {
        return $this->assertNullableString('reason');
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
