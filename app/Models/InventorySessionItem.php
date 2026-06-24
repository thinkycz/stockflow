<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\InventorySessionItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class InventorySessionItem extends BaseModel
{
    /** @use HasFactory<InventorySessionItemFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'inventory_session_items';

    /**
     * Search by note text. The model has a single free-form text column
     * so the search is bounded to that field.
     *
     * @param Builder<InventorySessionItem> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where('note', 'like', '%' . $search . '%');
    }

    /**
     * Restrict the query to a curated set of columns for list views.
     *
     * @param Builder<InventorySessionItem> $query
     *
     * @return Builder<InventorySessionItem>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'session_id', 'item_id', 'quantity', 'note', 'created_at', 'updated_at']);
    }

    /**
     * Session relationship.
     *
     * @return BelongsTo<InventorySession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(InventorySession::class, 'session_id');
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
     * Loaded or queried session.
     */
    public function getSession(): InventorySession
    {
        if ($this->relationLoaded('session')) {
            return $this->assertRelationship('session', InventorySession::class);
        }

        return Typer::assertInstance($this->session()->first(), InventorySession::class);
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
        return $this->assertInt('quantity');
    }

    /**
     * Note getter.
     */
    public function getNote(): string|null
    {
        return Typer::assertNullableString($this->getAttribute('note'));
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
        ];
    }
}
