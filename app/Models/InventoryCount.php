<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Database\Factories\InventoryCountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class InventoryCount extends BaseModel
{
    use BelongsToUser;
    /** @use HasFactory<InventoryCountFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'inventory_counts';

    /**
     * Scope a search to nothing (inventory counts are listed by store/item, no text search).
     *
     * @param Builder<InventoryCount> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        // No-op: rows are looked up through the parent store and item.
    }

    /**
     * Restrict the query to a curated set of columns for list views.
     *
     * @param Builder<InventoryCount> $query
     *
     * @return Builder<InventoryCount>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select([
            'id',
            'user_id',
            'store_id',
            'item_id',
            'quantity',
            'counted_at',
            'created_by',
            'note',
            'created_at',
            'updated_at',
        ]);
    }

    /**
     * Filter by store.
     *
     * @param Builder<InventoryCount> $query
     */
    public static function scopeForStore(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    /**
     * Filter by item.
     *
     * @param Builder<InventoryCount> $query
     */
    public static function scopeForItem(Builder $query, int $itemId): void
    {
        $query->where('item_id', $itemId);
    }

    /**
     * Filter counts taken on or after the given timestamp.
     *
     * @param Builder<InventoryCount> $query
     */
    public static function scopeSince(Builder $query, Carbon $since): void
    {
        $query->where('counted_at', '>=', $since->toDateTimeString());
    }

    /**
     * Filter counts taken between the given timestamps (inclusive).
     *
     * @param Builder<InventoryCount> $query
     */
    public static function scopeBetween(Builder $query, Carbon $from, Carbon $to): void
    {
        $query->where('counted_at', '>=', $from->toDateTimeString())
            ->where('counted_at', '<=', $to->toDateTimeString());
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
     * Item relationship.
     *
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
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
     * Loaded or queried store.
     */
    public function getStore(): Store
    {
        if ($this->relationLoaded('store')) {
            return $this->assertRelationship('store', Store::class);
        }

        return Typer::assertInstance($this->store()->first(), Store::class);
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
     * Loaded or queried creator (may be null if the user was deleted).
     */
    public function getCreator(): User|null
    {
        if ($this->relationLoaded('creator')) {
            return $this->assertNullableRelation('creator', User::class);
        }

        return $this->creator()->first();
    }

    /**
     * User id getter.
     */
    public function getUserId(): int
    {
        return $this->assertInt('user_id');
    }

    /**
     * Store id getter.
     */
    public function getStoreId(): int
    {
        return $this->assertInt('store_id');
    }

    /**
     * Item id getter.
     */
    public function getItemId(): int
    {
        return $this->assertInt('item_id');
    }

    /**
     * Quantity getter.
     */
    public function getQuantity(): int
    {
        return Typer::assertInt($this->getAttribute('quantity'));
    }

    /**
     * Counted-at getter (Carbon).
     */
    public function getCountedAt(): Carbon
    {
        $value = $this->getAttribute('counted_at');

        if ($value instanceof Carbon) {
            return $value;
        }

        return Carbon::parse(Typer::assertString($value));
    }

    /**
     * Note getter.
     */
    public function getNote(): string|null
    {
        return $this->assertNullableString('note');
    }

    /**
     * Created-by getter.
     */
    public function getCreatedBy(): int|null
    {
        return $this->assertNullableInt('created_by');
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
            'counted_at' => 'datetime',
        ];
    }
}
