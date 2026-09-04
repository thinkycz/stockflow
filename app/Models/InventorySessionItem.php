<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementClassificationEnum;
use Database\Factories\InventorySessionItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
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
        return $query->select(['id', 'session_id', 'item_id', 'quantity', 'counted_at', 'opening_quantity', 'client_version', 'expected_quantity', 'quantity_difference', 'classification', 'observation_started_at', 'note', 'created_at', 'updated_at']);
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
        return $this->belongsTo(Item::class, 'item_id')->withTrashed();
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
     * Server-owned revision, stored in the original version column.
     */
    public function getRevision(): int
    {
        return Typer::parseInt($this->getAttribute('client_version'));
    }

    /**
     * Authoritative editable draft values and revision.
     *
     * @return array<string, float|int|string|null>
     */
    public function draftValues(): array
    {
        return [
            'item_id' => $this->getItemId(),
            'quantity' => $this->getQuantity(),
            'classification' => $this->getClassification()?->value,
            'note' => $this->getNote(),
            'revision' => $this->getRevision(),
            'counted_at' => $this->getCountedAt()?->toJSON(),
            'expected_quantity' => $this->getExpectedQuantity(),
            'difference' => $this->getQuantityDifference(),
        ];
    }

    /**
     * Quantity getter.
     */
    public function getQuantity(): float|int
    {
        return $this->decimalNumber($this->getAttribute('quantity'));
    }

    /**
     * Expected quantity before the physical count.
     */
    public function getExpectedQuantity(): float|int|null
    {
        $value = $this->getAttribute('expected_quantity');

        return $value === null ? null : $this->decimalNumber($value);
    }

    /**
     * Timestamp of this row's physical count.
     */
    public function getCountedAt(): Carbon|null
    {
        return Typer::assertNullableCarbon($this->getAttribute('counted_at'));
    }

    /**
     * Counted quantity minus expected quantity.
     */
    public function getQuantityDifference(): float|int|null
    {
        $value = $this->getAttribute('quantity_difference');

        return $value === null ? null : $this->decimalNumber($value);
    }

    /**
     * Classification selected for a non-zero difference.
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
     * Start of the closed physical-count interval.
     */
    public function getObservationStartedAt(): Carbon|null
    {
        return Typer::assertNullableCarbon($this->getAttribute('observation_started_at'));
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
            'counted_at' => 'datetime',
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
