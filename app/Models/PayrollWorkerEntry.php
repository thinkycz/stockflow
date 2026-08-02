<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thinkycz\LaravelCore\Models\BaseModel;

class PayrollWorkerEntry extends BaseModel
{
    /**
     * The table associated with the model.
     */
    protected $table = 'payroll_worker_entries';

    /**
     * Scope entries by worker id.
     *
     * @param Builder<PayrollWorkerEntry> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where('worker_id', 'like', '%' . $search . '%');
    }

    /**
     * Restrict the query to entry columns.
     *
     * @param Builder<PayrollWorkerEntry> $query
     *
     * @return Builder<PayrollWorkerEntry>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'payroll_report_id', 'worker_id', 'created_at', 'updated_at']);
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
     * Payroll report id getter.
     */
    public function getPayrollReportId(): int
    {
        return $this->assertInt('payroll_report_id');
    }

    /**
     * Worker id getter.
     */
    public function getWorkerId(): int
    {
        return $this->assertInt('worker_id');
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
