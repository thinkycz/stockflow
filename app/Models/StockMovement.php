<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementTypeEnum;
use App\Models\Concerns\BelongsToUser;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class StockMovement extends BaseModel
{
    use BelongsToUser;
    /** @use HasFactory<StockMovementFactory> */
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
        $query->where('number', 'like', '%' . $search . '%');
    }

    /**
     * Restrict the query to a curated set of columns for list views.
     *
     * @param Builder<StockMovement> $query
     *
     * @return Builder<StockMovement>
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
     * Loaded or queried destination store.
     */
    public function getStore(): Store|null
    {
        if ($this->relationLoaded('store')) {
            return $this->assertNullableRelation('store', Store::class);
        }

        return $this->store()->first();
    }

    /**
     * Loaded or queried source store.
     */
    public function getSourceStore(): Store|null
    {
        if ($this->relationLoaded('sourceStore')) {
            return $this->assertNullableRelation('sourceStore', Store::class);
        }

        return $this->sourceStore()->first();
    }

    /**
     * Loaded or queried creator.
     */
    public function getCreator(): User|null
    {
        if ($this->relationLoaded('creator')) {
            return $this->assertNullableRelation('creator', User::class);
        }

        return $this->creator()->first();
    }

    /**
     * Loaded or queried movement rows.
     *
     * @return Collection<array-key, StockMovementItem>
     */
    public function getMovementItems(): Collection
    {
        if ($this->relationLoaded('movementItems')) {
            return $this->assertRelationshipCollection('movementItems', StockMovementItem::class);
        }

        return $this->movementItems()->get();
    }

    /**
     * Loaded or queried movement items.
     *
     * @return Collection<array-key, Item>
     */
    public function getItems(): Collection
    {
        if ($this->relationLoaded('items')) {
            return $this->assertRelationshipCollection('items', Item::class);
        }

        return $this->items()->get();
    }

    /**
     * Pivot quantity getter for item detail movement rows.
     */
    public function getPivotQuantity(): int|null
    {
        return Typer::parseNullableInt($this->readPivotAttribute('quantity'));
    }

    /**
     * Pivot quantity-before getter for adjustment rows.
     */
    public function getPivotQuantityBefore(): int|null
    {
        return Typer::parseNullableInt($this->readPivotAttribute('quantity_before'));
    }

    /**
     * Pivot quantity-after getter for adjustment rows.
     */
    public function getPivotQuantityAfter(): int|null
    {
        return Typer::parseNullableInt($this->readPivotAttribute('quantity_after'));
    }

    /**
     * Pivot quantity-difference getter for movement rows.
     */
    public function getPivotQuantityDifference(): int|null
    {
        return Typer::parseNullableInt($this->readPivotAttribute('quantity_difference'));
    }

    /**
     * Pivot adjustment reason getter.
     */
    public function getPivotAdjustmentReason(): string|null
    {
        return Typer::assertNullableString($this->readPivotAttribute('adjustment_reason'));
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
    public function getTotalQuantity(): int
    {
        return Typer::assertInt($this->getAttribute('total_quantity'));
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
            'total_quantity' => 'integer',
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

    /**
     * Read a belongs-to-many pivot attribute without triggering Eloquent accessors.
     */
    private function readPivotAttribute(string $key): mixed
    {
        $pivot = $this->getAttribute('pivot');

        if (!$pivot instanceof Pivot) {
            return null;
        }

        return $pivot->getAttribute($key);
    }
}
