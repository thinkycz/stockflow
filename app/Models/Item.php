<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItemStockStatusEnum;
use App\Models\Concerns\BelongsToUser;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class Item extends BaseModel
{
    use BelongsToUser;
    /** @use HasFactory<ItemFactory> */
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
        $query->where(static function (Builder $query) use ($search): void {
            $query->where('title', 'like', '%' . $search . '%')
                ->orWhere('sku', 'like', '%' . $search . '%');
        });
    }

    /**
     * Restrict the query to a curated set of columns for list views.
     *
     * @param Builder<Item> $query
     *
     * @return Builder<Item>
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
     * Loaded or queried stock movement items.
     *
     * @return Collection<array-key, StockMovementItem>
     */
    public function getStockMovementItems(): Collection
    {
        if ($this->relationLoaded('stockMovementItems')) {
            return $this->assertRelationshipCollection('stockMovementItems', StockMovementItem::class);
        }

        return $this->stockMovementItems()->get();
    }

    /**
     * Loaded or queried stock movements.
     *
     * @return Collection<array-key, StockMovement>
     */
    public function getStockMovements(): Collection
    {
        if ($this->relationLoaded('stockMovements')) {
            return $this->assertRelationshipCollection('stockMovements', StockMovement::class);
        }

        return $this->stockMovements()->get();
    }

    /**
     * Loaded or queried per-store stock rows.
     *
     * @return Collection<array-key, StoreItem>
     */
    public function getStoreItems(): Collection
    {
        if ($this->relationLoaded('storeItems')) {
            return $this->assertRelationshipCollection('storeItems', StoreItem::class);
        }

        return $this->storeItems()->get();
    }

    /**
     * Loaded or queried stores holding this item.
     *
     * @return Collection<array-key, Store>
     */
    public function getStores(): Collection
    {
        if ($this->relationLoaded('stores')) {
            return $this->assertRelationshipCollection('stores', Store::class);
        }

        return $this->stores()->get();
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
    public function getWarehouseQuantity(): int
    {
        $cached = $this->getAttribute('warehouse_quantity_sum');

        if ($cached !== null) {
            return Typer::parseInt($cached);
        }

        $storeQuery = Store::query();
        Store::scopeForUser($storeQuery, $this->getUserId());

        $warehouseIds = $storeQuery
            ->where('is_warehouse', true)
            ->pluck('id');

        if ($warehouseIds->isEmpty()) {
            return 0;
        }

        return Typer::parseInt($this->storeItems()
            ->whereIn('store_id', $warehouseIds)
            ->sum('quantity'));
    }

    /**
     * Total quantity across all of the owner's stores.
     */
    public function getTotalQuantity(): int
    {
        $cached = $this->getAttribute('total_quantity_sum');

        if ($cached !== null) {
            return Typer::parseInt($cached);
        }

        return Typer::parseInt($this->storeItems()->sum('quantity'));
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
