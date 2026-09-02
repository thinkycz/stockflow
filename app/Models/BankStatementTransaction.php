<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BankStatementTransactionCategoryEnum;
use Database\Factories\BankStatementTransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class BankStatementTransaction extends BaseModel
{
    /** @use HasFactory<BankStatementTransactionFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'bank_statement_transactions';

    /**
     * Search transaction descriptions.
     *
     * @param Builder<BankStatementTransaction> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where('item_type', 'like', '%' . $search . '%');
    }

    /**
     * Restrict list queries to explicit columns.
     *
     * @param Builder<BankStatementTransaction> $query
     *
     * @return Builder<BankStatementTransaction>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select([
            'id', 'bank_statement_id', 'position', 'booked_on', 'executed_on', 'item_type', 'amount',
            'currency', 'category', 'sales_from', 'sales_to', 'manually_edited', 'created_at', 'updated_at',
        ]);
    }

    /**
     * Parent bank statement relationship.
     *
     * @return BelongsTo<BankStatement, $this>
     */
    public function bankStatement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }

    /**
     * Loaded or queried parent bank statement.
     */
    public function getBankStatement(): BankStatement
    {
        if ($this->relationLoaded('bankStatement')) {
            return $this->assertRelationship('bankStatement', BankStatement::class);
        }

        return Typer::assertInstance($this->bankStatement()->first(), BankStatement::class);
    }

    /**
     * Parent statement id.
     */
    public function getBankStatementId(): int
    {
        return $this->assertInt('bank_statement_id');
    }

    /**
     * Row position in the source PDF.
     */
    public function getPosition(): int
    {
        return $this->assertInt('position');
    }

    /**
     * Booking date.
     */
    public function getBookedOn(): Carbon
    {
        return $this->assertCarbon('booked_on');
    }

    /**
     * Execution date when supplied.
     */
    public function getExecutedOn(): Carbon|null
    {
        return $this->assertNullableCarbon('executed_on');
    }

    /**
     * Bank transaction type.
     */
    public function getItemType(): string
    {
        return $this->assertString('item_type');
    }

    /**
     * Signed amount as a decimal string.
     */
    public function getAmount(): string
    {
        return Typer::assertString($this->getAttribute('amount'));
    }

    /**
     * Transaction currency.
     */
    public function getCurrency(): string
    {
        return $this->assertString('currency');
    }

    /**
     * Reconciliation category.
     */
    public function getCategory(): BankStatementTransactionCategoryEnum
    {
        return BankStatementTransactionCategoryEnum::from($this->assertString('category'));
    }

    /**
     * Suggested sales period start.
     */
    public function getSalesFrom(): Carbon|null
    {
        return $this->assertNullableCarbon('sales_from');
    }

    /**
     * Suggested sales period end.
     */
    public function getSalesTo(): Carbon|null
    {
        return $this->assertNullableCarbon('sales_to');
    }

    /**
     * Counterparty display name.
     */
    public function getCounterpartyName(): string|null
    {
        return $this->assertNullableString('counterparty_name');
    }

    /**
     * Counterparty account.
     */
    public function getCounterpartyAccount(): string|null
    {
        return $this->assertNullableString('counterparty_account');
    }

    /**
     * Variable symbol.
     */
    public function getVariableSymbol(): string|null
    {
        return $this->assertNullableString('variable_symbol');
    }

    /**
     * Constant symbol.
     */
    public function getConstantSymbol(): string|null
    {
        return $this->assertNullableString('constant_symbol');
    }

    /**
     * Specific symbol.
     */
    public function getSpecificSymbol(): string|null
    {
        return $this->assertNullableString('specific_symbol');
    }

    /**
     * Bank description.
     */
    public function getDescription(): string|null
    {
        return $this->assertNullableString('description');
    }

    /**
     * AI review note.
     */
    public function getReviewNote(): string|null
    {
        return $this->assertNullableString('review_note');
    }

    /**
     * Whether an administrator changed the parsed row.
     */
    public function isManuallyEdited(): bool
    {
        return (bool) $this->getAttribute('manually_edited');
    }

    /**
     * Model casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'booked_on' => 'date',
            'executed_on' => 'date',
            'amount' => 'decimal:2',
            'counterparty_name' => 'encrypted',
            'counterparty_account' => 'encrypted',
            'variable_symbol' => 'encrypted',
            'constant_symbol' => 'encrypted',
            'specific_symbol' => 'encrypted',
            'description' => 'encrypted',
            'sales_from' => 'date',
            'sales_to' => 'date',
            'review_note' => 'encrypted',
            'source_payload' => 'encrypted:array',
            'manually_edited' => 'boolean',
        ];
    }
}
