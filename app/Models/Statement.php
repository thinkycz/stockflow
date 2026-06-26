<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Database\Factories\StatementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class Statement extends BaseModel
{
    use BelongsToUser;
    /** @use HasFactory<StatementFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'statements';

    /**
     * Scope a query to only include statements matching the search term.
     *
     * @param Builder<Statement> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where(static function (Builder $query) use ($search): void {
            $query->where('year', Typer::parseInt($search))
                ->orWhere('month', Typer::parseInt($search));
        });
    }

    /**
     * Restrict the query to a curated set of columns for list views.
     *
     * @param Builder<Statement> $query
     *
     * @return Builder<Statement>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'user_id', 'store_id', 'year', 'month', 'created_at', 'updated_at']);
    }

    /**
     * Filter by store.
     *
     * @param Builder<Statement> $query
     */
    public static function scopeForStore(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    /**
     * Filter by year and month.
     *
     * @param Builder<Statement> $query
     */
    public static function scopeForMonth(Builder $query, int $year, int $month): void
    {
        $query->where('year', $year)->where('month', $month);
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
     * Daily rows relationship.
     *
     * @return HasMany<StatementDay, $this>
     */
    public function days(): HasMany
    {
        return $this->hasMany(StatementDay::class, 'statement_id');
    }

    /**
     * Version snapshots relationship.
     *
     * @return HasMany<StatementVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(StatementVersion::class, 'statement_id');
    }

    /**
     * Version day rows through versions.
     *
     * @return HasManyThrough<StatementVersionDay, StatementVersion, $this>
     */
    public function versionDays(): HasManyThrough
    {
        return $this->hasManyThrough(StatementVersionDay::class, StatementVersion::class, 'statement_id', 'version_id');
    }

    /**
     * Loaded or queried destination store.
     */
    public function getStore(): Store
    {
        if ($this->relationLoaded('store')) {
            return $this->assertRelationship('store', Store::class);
        }

        return Typer::assertInstance($this->store()->first(), Store::class);
    }

    /**
     * Loaded or queried daily rows.
     *
     * @return Collection<array-key, StatementDay>
     */
    public function getDays(): Collection
    {
        if ($this->relationLoaded('days')) {
            return $this->assertRelationshipCollection('days', StatementDay::class);
        }

        return $this->days()->get();
    }

    /**
     * Store id getter.
     */
    public function getStoreId(): int
    {
        return $this->assertInt('store_id');
    }

    /**
     * User id getter.
     */
    public function getUserId(): int
    {
        return $this->assertInt('user_id');
    }

    /**
     * Year getter.
     */
    public function getYear(): int
    {
        return $this->assertInt('year');
    }

    /**
     * Month getter.
     */
    public function getMonth(): int
    {
        return $this->assertInt('month');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
        ];
    }
}
