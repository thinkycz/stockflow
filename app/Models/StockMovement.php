<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementTypeEnum;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

/**
 * @property int $id
 * @property int $user_id
 * @property string $number
 * @property string $type
 * @property int|null $store_id
 * @property int|null $source_store_id
 * @property string|null $note
 * @property int|null $created_by
 * @property string $total_quantity
 * @property string $total_value
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property Store|null $store
 * @property Store|null $sourceStore
 * @property User|null $creator
 * @property Collection<array-key, StockMovementItem> $movementItems
 * @property Collection<array-key, Item> $items
 */
class StockMovement extends BaseModel
{
    use BelongsToUser;
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'stock_movements';

    /**
     * Scope a query to only include movements matching the search term.
     *
     * @param Builder<StockMovement> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $like = '%' . $search . '%';

        $query->where('number', 'like', $like);
    }

    /**
     * Restrict the query to a curated set of columns for list views.
     *
     * @param Builder<StockMovement> $query
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'user_id', 'number', 'type', 'store_id', 'source_store_id', 'note', 'created_by', 'total_quantity', 'total_value', 'created_at', 'updated_at']);
    }

    /**
     * Filter by type.
     *
     * @param Builder<StockMovement> $query
     */
    public static function scopeOfType(Builder $query, StockMovementTypeEnum $type): void
    {
        $query->where('type', $type->value);
    }

    /**
     * Filter by store.
     *
     * @param Builder<StockMovement> $query
     */
    public static function scopeForStore(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    /**
     * Filter movements created on or after a date.
     *
     * @param Builder<StockMovement> $query
     */
    public static function scopeFromDate(Builder $query, string $date): void
    {
        $query->where('created_at', '>=', $date);
    }

    /**
     * Filter movements created on or before a date.
     *
     * @param Builder<StockMovement> $query
     */
    public static function scopeUntilDate(Builder $query, string $date): void
    {
        $query->where('created_at', '<=', $date);
    }

    /**
     * Store relationship.
     *
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /**
     * Source warehouse relationship (outgoing transfers).
     *
     * @return BelongsTo<Store, $this>
     */
    public function sourceStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'source_store_id');
    }

    /**
     * Creator relationship.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Movement items relationship.
     *
     * @return HasMany<StockMovementItem, $this>
     */
    public function movementItems(): HasMany
    {
        return $this->hasMany(StockMovementItem::class, 'stock_movement_id');
    }

    /**
     * Items relationship (through movement items).
     *
     * @return BelongsToMany<Item, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'stock_movement_items', 'stock_movement_id', 'item_id')
            ->withPivot(['quantity', 'total', 'quantity_before', 'quantity_after', 'quantity_difference', 'adjustment_reason']);
    }

    /**
     * Number getter.
     */
    public function getNumber(): string
    {
        return $this->assertString('number');
    }

    /**
     * Type getter.
     */
    public function getType(): StockMovementTypeEnum
    {
        $value = $this->getAttribute('type');

        if ($value instanceof StockMovementTypeEnum) {
            return $value;
        }

        return StockMovementTypeEnum::from(Typer::assertString($value));
    }

    /**
     * Display label key for UI (incoming, outgoing, transfer, adjustment).
     */
    public function getDisplayLabelKey(): string
    {
        $type = $this->getType();

        if ($type === StockMovementTypeEnum::ADJUSTMENT) {
            return 'adjustment';
        }

        if ($type === StockMovementTypeEnum::INCOMING) {
            return 'incoming';
        }

        $sourceStore = $this->resolveSourceStore();

        if ($sourceStore instanceof Store && !$sourceStore->isWarehouse()) {
            return 'transfer';
        }

        return 'outgoing';
    }

    /**
     * Store id getter.
     */
    public function getStoreId(): int|null
    {
        return $this->assertNullableInt('store_id');
    }

    /**
     * Source store id getter.
     */
    public function getSourceStoreId(): int|null
    {
        return $this->assertNullableInt('source_store_id');
    }

    /**
     * Created at date string getter (Y-m-d).
     */
    public function getCreatedAtDate(): string
    {
        return $this->getCreatedAt()->toDateString();
    }

    /**
     * Note getter.
     */
    public function getNote(): string|null
    {
        return $this->assertNullableString('note');
    }

    /**
     * Created by getter.
     */
    public function getCreatedBy(): int|null
    {
        return $this->assertNullableInt('created_by');
    }

    /**
     * Total quantity getter.
     */
    public function getTotalQuantity(): float
    {
        return (float) Typer::assertString($this->getAttribute('total_quantity'));
    }

    /**
     * Total value getter.
     */
    public function getTotalValue(): float
    {
        return (float) Typer::assertString($this->getAttribute('total_value'));
    }

    /**
     * Item count getter.
     */
    public function getItemsCount(): int
    {
        $count = $this->getAttribute('movement_items_count');

        if ($count !== null) {
            return Typer::assertInt($count);
        }

        return $this->movementItems()->count();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_quantity' => 'decimal:3',
            'total_value' => 'decimal:2',
        ];
    }

    /**
     * Resolve the source store, eager-loading the relation if needed.
     */
    private function resolveSourceStore(): Store|null
    {
        if ($this->relationLoaded('sourceStore')) {
            $source = $this->getRelation('sourceStore');

            return $source instanceof Store ? $source : null;
        }

        $sourceId = $this->getSourceStoreId();

        if ($sourceId === null) {
            return null;
        }

        $userId = Typer::assertInt($this->getAttribute('user_id'));

        return Store::query()->where('user_id', $userId)->find($sourceId);
    }
}
