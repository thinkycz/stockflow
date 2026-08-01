<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FinancialDirectionEnum;
use Database\Factories\FinancialReportManualRowFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class FinancialReportManualRow extends BaseModel
{
    /** @use HasFactory<FinancialReportManualRowFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'financial_report_manual_rows';

    /**
     * @param Builder<FinancialReportManualRow> $query
     */
    public static function scopeSearch(Builder $query, string $search): void { $query->where('label', 'like', '%' . $search . '%'); }

    /**
     * Restrict the query to manual-row columns.
     *
     * @param Builder<FinancialReportManualRow> $query
     *
     * @return Builder<FinancialReportManualRow>
     */
    public static function querySelect(Builder $query): Builder { return $query->select(['id', 'financial_report_id', 'direction', 'label', 'occurred_on', 'amount', 'note', 'copied_from_row_id', 'created_at', 'updated_at']); }

    /**
     * @return BelongsTo<FinancialReport, $this>
     */
    public function financialReport(): BelongsTo { return $this->belongsTo(FinancialReport::class); }

    /**
     * Get report id.
     */
    public function getFinancialReportId(): int { return $this->assertInt('financial_report_id'); }

    /**
     * Get direction.
     */
    public function getDirection(): FinancialDirectionEnum { return FinancialDirectionEnum::from($this->assertString('direction')); }

    /**
     * Get label.
     */
    public function getLabel(): string { return $this->assertString('label'); }

    /**
     * Get occurrence date.
     */
    public function getOccurredOn(): string
    {
        $value = $this->getAttribute('occurred_on');

        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : Typer::assertString($value);
    }

    /**
     * Get amount.
     */
    public function getAmount(): float { return (float) Typer::assertString($this->getAttribute('amount')); }

    /**
     * Get note.
     */
    public function getNote(): string|null { return $this->assertNullableString('note'); }

    /**
     * Get copied source id.
     */
    public function getCopiedFromRowId(): int|null { return $this->assertNullableInt('copied_from_row_id'); }

    /**
     * @return array<string, string>
     */
    protected function casts(): array { return ['occurred_on' => 'date', 'amount' => 'decimal:2']; }
}
