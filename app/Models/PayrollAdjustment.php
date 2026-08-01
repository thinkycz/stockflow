<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PayrollAdjustmentTypeEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class PayrollAdjustment extends BaseModel
{
    /**
     * The table associated with the model.
     */
    protected $table = 'payroll_adjustments';

    /**
     * Scope adjustments by reason.
     *
     * @param Builder<PayrollAdjustment> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where('reason', 'like', '%' . $search . '%');
    }

    /**
     * Restrict the query to adjustment columns.
     *
     * @param Builder<PayrollAdjustment> $query
     *
     * @return Builder<PayrollAdjustment>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select([
            'id', 'payroll_report_id', 'worker_id', 'type', 'amount', 'reason',
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
     * Adjustment type.
     */
    public function getType(): PayrollAdjustmentTypeEnum
    {
        return PayrollAdjustmentTypeEnum::from($this->assertString('type'));
    }

    /**
     * Adjustment amount.
     */
    public function getAmount(): float
    {
        return (float) Typer::assertString($this->getAttribute('amount'));
    }

    /**
     * Adjustment reason.
     */
    public function getReason(): string
    {
        return $this->assertString('reason');
    }

    /**
     * Model casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }
}
