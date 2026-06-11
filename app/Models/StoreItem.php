<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\StoreItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class StoreItem extends BaseModel
{
    /** @use HasFactory<StoreItemFactory> */
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The table associated with the model.
     */
    protected $table = 'store_items';

    /**
     * Scope a query to only include store items matching the search term.
     *
     * @param Builder<StoreItem> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->whereHas('item', static function (Builder $query) use ($search): void {
            $query->where('title', 'like', '%' . $search . '%')->getQuery()
                ->orWhere('sku', 'like', '%' . $search . '%');
        });
    }

    /**
     * Restrict the query to a curated set of columns for list views.
     *
     * @param Builder<StoreItem> $query
     *
     * @return Builder<StoreItem>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'store_id', 'item_id', 'quantity']);
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
     * Quantity getter.
     */
    public function getQuantity(): float
    {
        return (float) Typer::assertString($this->getAttribute('quantity'));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }
}
