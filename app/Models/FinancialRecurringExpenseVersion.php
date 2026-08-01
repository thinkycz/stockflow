<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FinancialRecurringExpenseVersionFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class FinancialRecurringExpenseVersion extends BaseModel
{
    /** @use HasFactory<FinancialRecurringExpenseVersionFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'financial_recurring_expense_versions';

    /**
     * @param Builder<FinancialRecurringExpenseVersion> $query
     */
    public static function scopeSearch(Builder $query, string $search): void { $query->where('label', 'like', '%' . $search . '%'); }

    /**
     * @param Builder<FinancialRecurringExpenseVersion> $query
     *
     * @return Builder<FinancialRecurringExpenseVersion>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'financial_recurring_expense_id', 'effective_from', 'label', 'amount', 'due_day', 'note', 'created_at', 'updated_at']);
    }

    /**
     * Parent recurring expense.
     *
     * @return BelongsTo<FinancialRecurringExpense, $this>
     */
    public function recurringExpense(): BelongsTo { return $this->belongsTo(FinancialRecurringExpense::class, 'financial_recurring_expense_id'); }

    /**
     * Get recurring expense id.
     */
    public function getFinancialRecurringExpenseId(): int { return $this->assertInt('financial_recurring_expense_id'); }

    /**
     * Get effective month.
     */
    public function getEffectiveFrom(): string
    {
        $value = $this->getAttribute('effective_from');

        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : Typer::assertString($value);
    }

    /**
     * Get row label.
     */
    public function getLabel(): string { return $this->assertString('label'); }

    /**
     * Get fixed amount.
     */
    public function getAmount(): float { return (float) Typer::assertString($this->getAttribute('amount')); }

    /**
     * Get due day.
     */
    public function getDueDay(): int { return $this->assertInt('due_day'); }

    /**
     * Get optional note.
     */
    public function getNote(): string|null { return $this->assertNullableString('note'); }

    /**
     * @return array<string, string>
     */
    protected function casts(): array { return ['effective_from' => 'date', 'amount' => 'decimal:2', 'due_day' => 'integer']; }
}
