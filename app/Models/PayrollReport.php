<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PayrollReportStatusEnum;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class PayrollReport extends BaseModel
{
    use BelongsToUser;

    /**
     * The table associated with the model.
     */
    protected $table = 'payroll_reports';

    /**
     * Scope reports by month label.
     *
     * @param Builder<PayrollReport> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->whereRaw('CONCAT(year, \'-\', LPAD(month, 2, \'0\')) like ?', ['%' . $search . '%']);
    }

    /**
     * Restrict the query to report columns.
     *
     * @param Builder<PayrollReport> $query
     *
     * @return Builder<PayrollReport>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select([
            'id', 'user_id', 'store_id', 'year', 'month', 'status', 'snapshot',
            'closed_at', 'closed_by_user_id', 'reopened_at', 'reopened_by_user_id',
            'created_at', 'updated_at',
        ]);
    }

    /**
     * Adjustment relationship.
     *
     * @return HasMany<PayrollAdjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class);
    }

    /**
     * Loaded adjustments.
     *
     * @return Collection<array-key, PayrollAdjustment>
     */
    public function getAdjustments(): Collection
    {
        return $this->relationLoaded('adjustments')
            ? $this->assertRelationshipCollection('adjustments', PayrollAdjustment::class)
            : $this->adjustments()->get();
    }

    /**
     * Owning user id.
     */
    public function getUserId(): int
    {
        return $this->assertInt('user_id');
    }

    /**
     * Store id.
     */
    public function getStoreId(): int
    {
        return $this->assertInt('store_id');
    }

    /**
     * Report year.
     */
    public function getYear(): int
    {
        return $this->assertInt('year');
    }

    /**
     * Report month.
     */
    public function getMonth(): int
    {
        return $this->assertInt('month');
    }

    /**
     * Report status.
     */
    public function getStatus(): PayrollReportStatusEnum
    {
        return PayrollReportStatusEnum::from($this->assertString('status'));
    }

    /**
     * Whether the report is closed.
     */
    public function isClosed(): bool
    {
        return $this->getStatus() === PayrollReportStatusEnum::CLOSED;
    }

    /**
     * Saved snapshot.
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
     * Latest close time.
     */
    public function getClosedAt(): Carbon|null
    {
        return Typer::assertNullableCarbon($this->getAttribute('closed_at'));
    }

    /**
     * Latest reopen time.
     */
    public function getReopenedAt(): Carbon|null
    {
        return Typer::assertNullableCarbon($this->getAttribute('reopened_at'));
    }

    /**
     * Model casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'snapshot' => 'array',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }
}
