<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FinancialSourceTypeEnum;
use Database\Factories\FinancialReportOverrideFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class FinancialReportOverride extends BaseModel
{
    /** @use HasFactory<FinancialReportOverrideFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'financial_report_overrides';

    /**
     * @param Builder<FinancialReportOverride> $query
     */
    public static function scopeSearch(Builder $query, string $search): void { $query->where('source_key', 'like', '%' . $search . '%'); }

    /**
     * Restrict the query to override columns.
     *
     * @param Builder<FinancialReportOverride> $query
     *
     * @return Builder<FinancialReportOverride>
     */
    public static function querySelect(Builder $query): Builder { return $query->select(['id', 'financial_report_id', 'source_type', 'source_key', 'amount', 'created_at', 'updated_at']); }

    /**
     * @return BelongsTo<FinancialReport, $this>
     */
    public function financialReport(): BelongsTo { return $this->belongsTo(FinancialReport::class); }

    /**
     * Get report id.
     */
    public function getFinancialReportId(): int { return $this->assertInt('financial_report_id'); }

    /**
     * Get source type.
     */
    public function getSourceType(): FinancialSourceTypeEnum { return FinancialSourceTypeEnum::from($this->assertString('source_type')); }

    /**
     * Get source key.
     */
    public function getSourceKey(): string { return $this->assertString('source_key'); }

    /**
     * Get override amount.
     */
    public function getAmount(): float { return (float) Typer::assertString($this->getAttribute('amount')); }

    /**
     * @return array<string, string>
     */
    protected function casts(): array { return ['amount' => 'decimal:2']; }
}
