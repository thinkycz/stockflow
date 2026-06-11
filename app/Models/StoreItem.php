<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

/**
 * @property int $id
 * @property int $store_id
 * @property int $item_id
 * @property string $quantity
 * @property Store $store
 * @property Item $item
 */
class StoreItem extends BaseModel
{
    /** @use HasFactory<\Database\Factories\StoreItemFactory> */
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
        $like = '%' . $search . '%';

        $query->whereHas('item', static function (Builder $query) use ($like): void {
            $query->where('title', 'like', $like)->getQuery()
                ->orWhere('sku', 'like', $like);
        });
    }

    /**
     * Restrict the query to a curated set of columns for list views.
     *
     * @param Builder<StoreItem> $query
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
