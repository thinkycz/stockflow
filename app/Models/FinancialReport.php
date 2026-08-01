<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FinancialReportStatusEnum;
use App\Models\Concerns\BelongsToUser;
use Database\Factories\FinancialReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class FinancialReport extends BaseModel
{
    use BelongsToUser;
    /** @use HasFactory<FinancialReportFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'financial_reports';

    /**
     * Scope reports by their month label.
     *
     * @param Builder<FinancialReport> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->whereRaw('CONCAT(year, \'-\', LPAD(month, 2, \'0\')) like ?', ['%' . $search . '%']);
    }

    /**
     * Restrict the query to report columns.
     *
     * @param Builder<FinancialReport> $query
     *
     * @return Builder<FinancialReport>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'user_id', 'store_id', 'year', 'month', 'status', 'snapshot', 'closed_at', 'closed_by_user_id', 'reopened_at', 'reopened_by_user_id', 'created_at', 'updated_at']);
    }

    /**
     * Manual rows relationship.
     *
     * @return HasMany<FinancialReportManualRow, $this>
     */
    public function manualRows(): HasMany
    {
        return $this->hasMany(FinancialReportManualRow::class);
    }

    /**
     * Overrides relationship.
     *
     * @return HasMany<FinancialReportOverride, $this>
     */
    public function overrides(): HasMany
    {
        return $this->hasMany(FinancialReportOverride::class);
    }

    /**
     * Store relationship.
     *
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Loaded manual rows.
     *
     * @return Collection<array-key, FinancialReportManualRow>
     */
    public function getManualRows(): Collection
    {
        return $this->relationLoaded('manualRows')
            ? $this->assertRelationshipCollection('manualRows', FinancialReportManualRow::class)
            : $this->manualRows()->get();
    }

    /**
     * Loaded overrides.
     *
     * @return Collection<array-key, FinancialReportOverride>
     */
    public function getOverrides(): Collection
    {
        return $this->relationLoaded('overrides')
            ? $this->assertRelationshipCollection('overrides', FinancialReportOverride::class)
            : $this->overrides()->get();
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
     * Get report year.
     */
    public function getYear(): int { return $this->assertInt('year'); }

    /**
     * Get report month.
     */
    public function getMonth(): int { return $this->assertInt('month'); }

    /**
     * Get report status.
     */
    public function getStatus(): FinancialReportStatusEnum { return FinancialReportStatusEnum::from($this->assertString('status')); }

    /**
     * Whether the report is closed.
     */
    public function isClosed(): bool { return $this->getStatus() === FinancialReportStatusEnum::CLOSED; }

    /**
     * Get the saved snapshot.
     *
     * @return array<string, mixed>|null
     */
    public function getSnapshot(): array|null
    {
        $value = $this->getAttribute('snapshot');

        if ($value === null) {
            return null;
        }

        $snapshot = [];
        foreach (Typer::assertArray($value) as $key => $item) {
            $snapshot[Typer::assertString($key)] = $item;
        }

        return $snapshot;
    }

    /**
     * Get the latest close time.
     */
    public function getClosedAt(): Carbon|null { return Typer::assertNullableCarbon($this->getAttribute('closed_at')); }

    /**
     * Get the latest reopen time.
     */
    public function getReopenedAt(): Carbon|null { return Typer::assertNullableCarbon($this->getAttribute('reopened_at')); }

    /**
     * Get model casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['year' => 'integer', 'month' => 'integer', 'snapshot' => 'array', 'closed_at' => 'datetime', 'reopened_at' => 'datetime'];
    }
}
