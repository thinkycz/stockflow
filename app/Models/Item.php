<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItemStockStatusEnum;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string|null $sku
 * @property string|null $unit
 * @property string $purchase_price
 * @property string|null $description
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property Collection<array-key, StockMovementItem> $stockMovementItems
 * @property Collection<array-key, StockMovement> $stockMovements
 * @property Collection<array-key, StoreItem> $storeItems
 * @property Collection<array-key, Store> $stores
 */
class Item extends BaseModel
{
    use BelongsToUser;
    use HasFactory;

    /**
     * Low stock threshold constant.
     */
    public const int LOW_STOCK_THRESHOLD = 5;

    /**
     * The table associated with the model.
     */
    protected $table = 'items';

    /**
     * Scope a query to only include items matching the search term.
     *
     * @param Builder<Item> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $like = '%' . $search . '%';

        $query->where(static function (Builder $query) use ($like): void {
            $query->where('title', 'like', $like)->getQuery()
                ->orWhere('sku', 'like', $like);
        });
    }

    /**
     * Restrict the query to a curated set of columns for list views.
     *
     * @param Builder<Item> $query
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'user_id', 'title', 'sku', 'unit', 'purchase_price', 'description', 'created_at', 'updated_at'])
            ->withSum('storeItems as total_quantity_sum', 'quantity')
            ->addSelect([
                'warehouse_quantity_sum' => StoreItem::query()
                    ->selectRaw('SUM(quantity)')
                    ->join('stores', 'stores.id', '=', 'store_items.store_id')
                    ->whereColumn('store_items.item_id', 'items.id')
                    ->where('stores.is_warehouse', true),
            ]);
    }

    /**
     * Stock movement items relationship.
     *
     * @return HasMany<StockMovementItem, $this>
     */
    public function stockMovementItems(): HasMany
    {
        return $this->hasMany(StockMovementItem::class, 'item_id');
    }

    /**
     * Stock movements relationship (through items).
     *
     * @return BelongsToMany<StockMovement, $this>
     */
    public function stockMovements(): BelongsToMany
    {
        return $this->belongsToMany(StockMovement::class, 'stock_movement_items', 'item_id', 'stock_movement_id')
            ->withPivot(['quantity', 'total', 'quantity_before', 'quantity_after', 'quantity_difference', 'adjustment_reason']);
    }

    /**
     * Per-store stock rows.
     *
     * @return HasMany<StoreItem, $this>
     */
    public function storeItems(): HasMany
    {
        return $this->hasMany(StoreItem::class, 'item_id');
    }

    /**
     * Stores holding this item (with pivot quantity).
     *
     * @return BelongsToMany<Store, $this>
     */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_items', 'item_id', 'store_id')
            ->withPivot(['quantity']);
    }

    /**
     * Title getter.
     */
    public function getTitle(): string
    {
        return $this->assertString('title');
    }

    /**
     * Sku getter.
     */
    public function getSku(): string|null
    {
        return $this->assertNullableString('sku');
    }

    /**
     * Unit getter.
     */
    public function getUnit(): string|null
    {
        return $this->assertNullableString('unit');
    }

    /**
     * Total quantity across all of the owner's warehouse stores.
     */
    public function getWarehouseQuantity(): float
    {
        $cached = $this->getAttribute('warehouse_quantity_sum');

        if ($cached !== null) {
            return Typer::parseFloat($cached);
        }

        $warehouseIds = Store::query()
            ->forUser($this->assertInt('user_id'))
            ->where('is_warehouse', true)
            ->pluck('id');

        if ($warehouseIds->isEmpty()) {
            return 0.0;
        }

        return Typer::parseFloat($this->storeItems()
            ->whereIn('store_id', $warehouseIds)
            ->sum('quantity'));
    }

    /**
     * Total quantity across all of the owner's stores.
     */
    public function getTotalQuantity(): float
    {
        $cached = $this->getAttribute('total_quantity_sum');

        if ($cached !== null) {
            return Typer::parseFloat($cached);
        }

        return Typer::parseFloat($this->storeItems()->sum('quantity'));
    }

    /**
     * Purchase price getter.
     */
    public function getPurchasePrice(): float
    {
        return (float) Typer::assertString($this->getAttribute('purchase_price'));
    }

    /**
     * Description getter.
     */
    public function getDescription(): string|null
    {
        return $this->assertNullableString('description');
    }

    /**
     * User id getter.
     */
    public function getUserId(): int
    {
        return $this->assertInt('user_id');
    }

    /**
     * Total inventory value across all stores.
     */
    public function getTotalValue(): float
    {
        return $this->getTotalQuantity() * $this->getPurchasePrice();
    }

    /**
     * Stock status based on warehouse quantity.
     */
    public function getStockStatus(): ItemStockStatusEnum
    {
        return ItemStockStatusEnum::fromQuantity($this->getWarehouseQuantity());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
        ];
    }
}
