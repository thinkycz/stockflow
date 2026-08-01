<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Database\Factories\FinancialRecurringExpenseFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class FinancialRecurringExpense extends BaseModel
{
    use BelongsToUser;
    /** @use HasFactory<FinancialRecurringExpenseFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'financial_recurring_expenses';

    /**
     * @param Builder<FinancialRecurringExpense> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->whereHas('versions', static fn(Builder $versionQuery): Builder => $versionQuery->where('label', 'like', '%' . $search . '%'));
    }

    /**
     * @param Builder<FinancialRecurringExpense> $query
     *
     * @return Builder<FinancialRecurringExpense>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'user_id', 'store_id', 'starts_on', 'ends_before', 'created_at', 'updated_at']);
    }

    /**
     * Store relationship.
     *
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }

    /**
     * Version relationship.
     *
     * @return HasMany<FinancialRecurringExpenseVersion, $this>
     */
    public function versions(): HasMany { return $this->hasMany(FinancialRecurringExpenseVersion::class)->orderBy('effective_from'); }

    /**
     * Get loaded versions.
     *
     * @return Collection<array-key, FinancialRecurringExpenseVersion>
     */
    public function getVersions(): Collection
    {
        return $this->relationLoaded('versions')
            ? $this->assertRelationshipCollection('versions', FinancialRecurringExpenseVersion::class)
            : $this->versions()->get();
    }

    /**
     * Get owning user id.
     */
    public function getUserId(): int { return $this->assertInt('user_id'); }

    /**
     * Get store id.
     */
    public function getStoreId(): int { return $this->assertInt('store_id'); }

    /**
     * Get inclusive start month.
     */
    public function getStartsOn(): string { return $this->dateString('starts_on'); }

    /**
     * Get exclusive ending month.
     */
    public function getEndsBefore(): string|null
    {
        return $this->getAttribute('ends_before') === null ? null : $this->dateString('ends_before');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array { return ['starts_on' => 'date', 'ends_before' => 'date']; }

    /**
     * Normalize a date attribute.
     */
    private function dateString(string $attribute): string
    {
        $value = $this->getAttribute($attribute);

        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : Typer::assertString($value);
    }
}
