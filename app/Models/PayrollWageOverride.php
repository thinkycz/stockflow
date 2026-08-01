<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class PayrollWageOverride extends BaseModel
{
    /**
     * The table associated with the model.
     */
    protected $table = 'payroll_wage_overrides';

    /**
     * Scope overrides by worker id.
     *
     * @param Builder<PayrollWageOverride> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where('worker_id', 'like', '%' . $search . '%');
    }

    /**
     * Restrict the query to override columns.
     *
     * @param Builder<PayrollWageOverride> $query
     *
     * @return Builder<PayrollWageOverride>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select([
            'id', 'payroll_report_id', 'worker_id', 'hours', 'hourly_rate',
            'created_at', 'updated_at',
        ]);
    }

    /**
     * Parent report relationship.
     *
     * @return BelongsTo<PayrollReport, $this>
     */
    public function payrollReport(): BelongsTo
    {
        return $this->belongsTo(PayrollReport::class);
    }

    /**
     * Worker relationship.
     *
     * @return BelongsTo<Worker, $this>
     */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    /**
     * Parent report id.
     */
    public function getPayrollReportId(): int
    {
        return $this->assertInt('payroll_report_id');
    }

    /**
     * Worker id.
     */
    public function getWorkerId(): int
    {
        return $this->assertInt('worker_id');
    }

    /**
     * Manually payable hours.
     */
    public function getHours(): float
    {
        return Typer::parseFloat($this->getAttribute('hours'));
    }

    /**
     * Manually payable hourly rate.
     */
    public function getHourlyRate(): float
    {
        return Typer::parseFloat($this->getAttribute('hourly_rate'));
    }

    /**
     * Model casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }
}
