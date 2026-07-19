<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Database\Factories\InventorySessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class InventorySession extends BaseModel
{
    use BelongsToUser;
    /** @use HasFactory<InventorySessionFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'inventory_sessions';

    /**
     * Scope a query to a specific store.
     *
     * @param Builder<InventorySession> $query
     */
    public static function scopeForStore(Builder $query, int|Store $store): void
    {
        $storeId = $store instanceof Store ? $store->getKey() : $store;

        $query->where($query->getModel()->getTable() . '.store_id', $storeId);
    }

    /**
     * Scope a query to a date range.
     *
     * @param Builder<InventorySession> $query
     */
    public static function scopeBetween(Builder $query, Carbon $from, Carbon $to): void
    {
        $table = $query->getModel()->getTable();

        $query->where($table . '.counted_at', '>=', $from->toDateTimeString())
            ->where($table . '.counted_at', '<=', $to->toDateTimeString());
    }

    /**
     * Search sessions by the note text.
     *
     * @param Builder<InventorySession> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where('note', 'like', '%' . $search . '%');
    }

    /**
     * Restrict the query to a curated set of columns for list views.
     *
     * @param Builder<InventorySession> $query
     *
     * @return Builder<InventorySession>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'user_id', 'store_id', 'active_store_key', 'created_by', 'status', 'started_at', 'counted_at', 'closed_at', 'cancelled_at', 'opening_snapshot', 'note', 'created_at', 'updated_at']);
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
     * Created-by user relationship.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Items relationship.
     *
     * @return HasMany<InventorySessionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InventorySessionItem::class, 'session_id');
    }

    /**
     * @return Collection<array-key, InventorySessionItem>
     */
    public function getItems(): Collection
    {
        if ($this->relationLoaded('items')) {
            return $this->assertRelationshipCollection('items', InventorySessionItem::class);
        }

        return $this->items()->get();
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
     * Owning user id.
     */
    public function getUserId(): int
    {
        return Typer::assertInt($this->getAttribute('user_id'));
    }

    /**
     * Counted-at getter.
     */
    public function getCountedAt(): Carbon
    {
        return Typer::assertCarbon($this->getAttribute('counted_at'));
    }

    /**
     * Workflow status getter.
     */
    public function getStatus(): string
    {
        return $this->assertString('status');
    }

    /**
     * Draft start timestamp getter.
     */
    public function getStartedAt(): Carbon
    {
        return Typer::assertCarbon($this->getAttribute('started_at'));
    }

    /**
     * Whether this session is still editable.
     */
    public function isDraft(): bool
    {
        return $this->getStatus() === 'draft';
    }

    /**
     * Note getter.
     */
    public function getNote(): string|null
    {
        return Typer::assertNullableString($this->getAttribute('note'));
    }

    /**
     * Created-by id getter.
     */
    public function getCreatedBy(): int|null
    {
        return Typer::assertNullableInt($this->getAttribute('created_by'));
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
            'started_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'opening_snapshot' => 'array',
        ];
    }
}
