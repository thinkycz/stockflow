<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdjustmentReasonEnum;
use Database\Factories\StockMovementItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class StockMovementItem extends BaseModel
{
    /** @use HasFactory<StockMovementItemFactory> */
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The table associated with the model.
     */
    protected $table = 'stock_movement_items';

    /**
     * Scope a search to nothing (no text search on this table).
     *
     * @param Builder<StockMovementItem> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        // No-op: rows are looked up through parent movement or item.
    }

    /**
     * Restrict the query to a curated set of columns for list views.
     *
     * @param Builder<StockMovementItem> $query
     *
     * @return Builder<StockMovementItem>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select([
            'id',
            'stock_movement_id',
            'item_id',
            'quantity',
            'total',
            'quantity_before',
            'quantity_after',
            'quantity_difference',
            'adjustment_reason',
        ]);
    }

    /**
     * Stock movement relationship.
     *
     * @return BelongsTo<StockMovement, $this>
     */
    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    /**
     * Item relationship.
     *
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /**
     * Stock movement id getter.
     */
    public function getStockMovementId(): int
    {
        return $this->assertInt('stock_movement_id');
    }

    /**
     * Item id getter.
     */
    public function getItemId(): int
    {
        return $this->assertInt('item_id');
    }

    /**
     * Loaded or queried stock movement.
     */
    public function getStockMovement(): StockMovement
    {
        if ($this->relationLoaded('stockMovement')) {
            return $this->assertRelationship('stockMovement', StockMovement::class);
        }

        return Typer::assertInstance($this->stockMovement()->first(), StockMovement::class);
    }

    /**
     * Loaded or queried item.
     */
    public function getItem(): Item
    {
        if ($this->relationLoaded('item')) {
            return $this->assertRelationship('item', Item::class);
        }

        return Typer::assertInstance($this->item()->first(), Item::class);
    }

    /**
     * Quantity getter.
     */
    public function getQuantity(): int|null
    {
        return Typer::assertNullableInt($this->getAttribute('quantity'));
    }

    /**
     * Total getter.
     */
    public function getTotal(): float
    {
        return (float) Typer::assertString($this->getAttribute('total'));
    }

    /**
     * Quantity before getter.
     */
    public function getQuantityBefore(): int|null
    {
        return Typer::assertNullableInt($this->getAttribute('quantity_before'));
    }

    /**
     * Quantity after getter.
     */
    public function getQuantityAfter(): int|null
    {
        return Typer::assertNullableInt($this->getAttribute('quantity_after'));
    }

    /**
     * Quantity difference getter.
     */
    public function getQuantityDifference(): int|null
    {
        return Typer::assertNullableInt($this->getAttribute('quantity_difference'));
    }

    /**
     * Adjustment reason getter.
     */
    public function getAdjustmentReason(): AdjustmentReasonEnum|null
    {
        $value = $this->getAttribute('adjustment_reason');

        if ($value === null) {
            return null;
        }

        return AdjustmentReasonEnum::from(Typer::assertString($value));
    }

    /**
     * Aggregate rows count getter.
     */
    public function getRowsCount(): int
    {
        return Typer::parseInt($this->getAttribute('rows_count'));
    }

    /**
     * Aggregate total quantity getter.
     */
    public function getAggregatedTotalQuantity(): int
    {
        return Typer::parseInt($this->getAttribute('total_quantity'));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'total' => 'decimal:2',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
            'quantity_difference' => 'integer',
        ];
    }
}
