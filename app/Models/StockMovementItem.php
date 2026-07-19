<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdjustmentReasonEnum;
use App\Enums\StockMovementClassificationEnum;
use Database\Factories\StockMovementItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
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
            'unit_cost',
            'unit_cost_estimated',
            'total',
            'quantity_before',
            'quantity_after',
            'quantity_difference',
            'adjustment_reason',
            'classification',
            'observation_started_at',
            'inventory_session_item_id',
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
     * Inventory row reconciled by this movement row.
     *
     * @return BelongsTo<InventorySessionItem, $this>
     */
    public function inventorySessionItem(): BelongsTo
    {
        return $this->belongsTo(InventorySessionItem::class, 'inventory_session_item_id');
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
    public function getQuantity(): float|int|null
    {
        $value = $this->getAttribute('quantity');

        return $value === null ? null : $this->decimalNumber($value);
    }

    /**
     * Total getter.
     */
    public function getTotal(): float
    {
        return (float) Typer::assertString($this->getAttribute('total'));
    }

    /**
     * Unit-cost snapshot getter.
     */
    public function getUnitCost(): float|null
    {
        $value = $this->getAttribute('unit_cost');

        return $value === null ? null : (float) Typer::assertScalar($value);
    }

    /**
     * Whether migration had to estimate the historical unit cost.
     */
    public function isUnitCostEstimated(): bool
    {
        return $this->assertBool('unit_cost_estimated');
    }

    /**
     * Quantity before getter.
     */
    public function getQuantityBefore(): float|int|null
    {
        $value = $this->getAttribute('quantity_before');

        return $value === null ? null : $this->decimalNumber($value);
    }

    /**
     * Quantity after getter.
     */
    public function getQuantityAfter(): float|int|null
    {
        $value = $this->getAttribute('quantity_after');

        return $value === null ? null : $this->decimalNumber($value);
    }

    /**
     * Quantity difference getter.
     */
    public function getQuantityDifference(): float|int|null
    {
        $value = $this->getAttribute('quantity_difference');

        return $value === null ? null : $this->decimalNumber($value);
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
     * Business classification of the stock difference.
     */
    public function getClassification(): StockMovementClassificationEnum|null
    {
        $value = $this->getAttribute('classification');

        if ($value === null) {
            return null;
        }

        return StockMovementClassificationEnum::from(Typer::assertString($value));
    }

    /**
     * Start of the closed observation interval.
     */
    public function getObservationStartedAt(): Carbon|null
    {
        return Typer::assertNullableCarbon($this->getAttribute('observation_started_at'));
    }

    /**
     * Linked inventory row id.
     */
    public function getInventorySessionItemId(): int|null
    {
        return $this->assertNullableInt('inventory_session_item_id');
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
    public function getAggregatedTotalQuantity(): float|int
    {
        return $this->decimalNumber($this->getAttribute('total_quantity'));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:4',
            'unit_cost_estimated' => 'boolean',
            'total' => 'decimal:2',
            'observation_started_at' => 'datetime',
        ];
    }

    /**
     * Preserve fractional database values while keeping whole numbers ergonomic.
     */
    private function decimalNumber(mixed $value): float|int
    {
        $number = (float) Typer::assertScalar($value);

        return $number === \floor($number) ? (int) $number : $number;
    }
}
