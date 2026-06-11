<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StoreStatusEnum;
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
 * @property string $name
 * @property string|null $address
 * @property string $status
 * @property bool $is_warehouse
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property Collection<array-key, StockMovement> $stockMovements
 * @property Collection<array-key, StoreItem> $storeItems
 * @property Collection<array-key, Item> $items
 */
class Store extends BaseModel
{
    use BelongsToUser;
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
        $like = '%' . $search . '%';

        $query->where(static function (Builder $query) use ($like): void {
            $query->where('name', 'like', $like)->getQuery()
                ->orWhere('address', 'like', $like);
        });
    }

    /**
     * Restrict the query to a curated set of columns for list views.
     *
     * @param Builder<Store> $query
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
