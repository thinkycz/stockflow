<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StoreStatusEnum;
use App\Models\Concerns\BelongsToUser;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class Store extends BaseModel
{
    use BelongsToUser;
    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'stores';

    /**
     * Scope a query to only include stores matching the search term.
     *
     * @param Builder<Store> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where(static function (Builder $query) use ($search): void {
            $query->where('name', 'like', '%' . $search . '%')
                ->orWhere('address', 'like', '%' . $search . '%');
        });
    }

    /**
     * Restrict the query to a curated set of columns for list views.
     *
     * @param Builder<Store> $query
     *
     * @return Builder<Store>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'user_id', 'name', 'address', 'status', 'is_warehouse', 'notes', 'created_at', 'updated_at']);
    }

    /**
     * Scope to only active stores.
     *
     * @param Builder<Store> $query
     */
    public static function scopeActive(Builder $query): void
    {
        $query->where('status', StoreStatusEnum::ACTIVE->value);
    }

    /**
     * Scope to warehouse stores.
     *
     * @param Builder<Store> $query
     */
    public static function scopeWarehouse(Builder $query): void
    {
        $query->where('is_warehouse', true);
    }

    /**
     * Scope to non-warehouse (retail) stores.
     *
     * @param Builder<Store> $query
     */
    public static function scopeRetail(Builder $query): void
    {
        $query->where('is_warehouse', false);
    }

    /**
     * Stock movements relationship.
     *
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'store_id');
    }

    /**
     * Per-item stock rows at this store.
     *
     * @return HasMany<StoreItem, $this>
     */
    public function storeItems(): HasMany
    {
        return $this->hasMany(StoreItem::class, 'store_id');
    }

    /**
     * Items stocked at this store (with pivot quantity).
     *
     * @return BelongsToMany<Item, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'store_items', 'store_id', 'item_id')
            ->withPivot(['quantity']);
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
     * Loaded or queried per-item stock rows.
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
     * Loaded or queried items stocked at this store.
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
     * Name getter.
     */
    public function getName(): string
    {
        return $this->assertString('name');
    }

    /**
     * Address getter.
     */
    public function getAddress(): string|null
    {
        return $this->assertNullableString('address');
    }

    /**
     * Status getter.
     */
    public function getStatus(): StoreStatusEnum
    {
        $value = $this->getAttribute('status');

        if ($value instanceof StoreStatusEnum) {
            return $value;
        }

        return StoreStatusEnum::from(Typer::assertString($value));
    }

    /**
     * Whether this store is the user's warehouse.
     */
    public function isWarehouse(): bool
    {
        return (bool) $this->getAttribute('is_warehouse');
    }

    /**
     * Warehouse owner id getter.
     */
    public function getWarehouseOwnerId(): int|null
    {
        return $this->assertNullableInt('warehouse_owner_id');
    }

    /**
     * Note getter.
     */
    public function getNotes(): string|null
    {
        return $this->assertNullableString('notes');
    }

    /**
     * User id getter.
     */
    public function getUserId(): int
    {
        return $this->assertInt('user_id');
    }

    /**
     * Mirror the `is_warehouse` flag into the `warehouse_owner_id`
     * unique-key column. Setting it on `creating` keeps the value
     * in sync regardless of which code path creates a store.
     */
    protected static function booted(): void
    {
        static::creating(static function (self $store): void {
            $ownerId = $store->isWarehouse() ? $store->getUserId() : null;
            $store->setAttribute('warehouse_owner_id', $ownerId);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StoreStatusEnum::class,
            'is_warehouse' => 'boolean',
        ];
    }
}
