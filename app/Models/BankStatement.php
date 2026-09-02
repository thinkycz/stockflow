<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BankStatementStatusEnum;
use App\Models\Concerns\BelongsToUser;
use Database\Factories\BankStatementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class BankStatement extends BaseModel
{
    use BelongsToUser;
    /** @use HasFactory<BankStatementFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'bank_statements';

    /**
     * Search statement metadata.
     *
     * @param Builder<BankStatement> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where(static function (Builder $query) use ($search): void {
            $query->where('statement_number', 'like', '%' . $search . '%')
                ->orWhere('bank_name', 'like', '%' . $search . '%')
                ->orWhere('original_name', 'like', '%' . $search . '%');
        });
    }

    /**
     * Restrict list queries to explicit columns.
     *
     * @param Builder<BankStatement> $query
     *
     * @return Builder<BankStatement>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select([
            'id', 'user_id', 'store_id', 'uploaded_by_user_id', 'status', 'bank_name', 'currency',
            'statement_number', 'period_from', 'period_to', 'original_name', 'last_error', 'attempt_count',
            'queued_at', 'started_at', 'parsed_at', 'confirmed_at', 'created_at', 'updated_at',
        ]);
    }

    /**
     * Scope statements to one store.
     *
     * @param Builder<BankStatement> $query
     */
    public static function scopeForStore(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    /**
     * Scope statements whose covered period intersects a calendar month.
     *
     * @param Builder<BankStatement> $query
     */
    public static function scopeForMonth(Builder $query, int $year, int $month): void
    {
        $from = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $to = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        $query->whereDate('period_from', '<=', $to)->whereDate('period_to', '>=', $from);
    }

    /**
     * Destination store relationship.
     *
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /**
     * Loaded or queried destination store.
     */
    public function getStore(): Store
    {
        if ($this->relationLoaded('store')) {
            return $this->assertRelationship('store', Store::class);
        }

        return Typer::assertInstance($this->store()->first(), Store::class);
    }

    /**
     * Parsed transactions relationship.
     *
     * @return HasMany<BankStatementTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(BankStatementTransaction::class, 'bank_statement_id');
    }

    /**
     * Loaded or queried parsed transactions.
     *
     * @return Collection<array-key, BankStatementTransaction>
     */
    public function getTransactions(): Collection
    {
        if ($this->relationLoaded('transactions')) {
            return $this->assertRelationshipCollection('transactions', BankStatementTransaction::class);
        }

        return $this->transactions()->orderBy('position')->get();
    }

    /**
     * Owning company id.
     */
    public function getUserId(): int
    {
        return $this->assertInt('user_id');
    }

    /**
     * Destination store id.
     */
    public function getStoreId(): int
    {
        return $this->assertInt('store_id');
    }

    /**
     * Current import state.
     */
    public function getStatus(): BankStatementStatusEnum
    {
        return BankStatementStatusEnum::from($this->assertString('status'));
    }

    /**
     * Covered period start.
     */
    public function getPeriodFrom(): Carbon|null
    {
        return $this->assertNullableCarbon('period_from');
    }

    /**
     * Covered period end.
     */
    public function getPeriodTo(): Carbon|null
    {
        return $this->assertNullableCarbon('period_to');
    }

    /**
     * Original private storage path.
     */
    public function getOriginalPath(): string
    {
        return $this->assertString('original_path');
    }

    /**
     * Original display filename.
     */
    public function getOriginalName(): string
    {
        return $this->assertString('original_name');
    }

    /**
     * Original MIME type.
     */
    public function getOriginalMime(): string
    {
        return $this->assertString('original_mime');
    }

    /**
     * Original byte size.
     */
    public function getOriginalSize(): int
    {
        return $this->assertInt('original_size');
    }

    /**
     * Statement number.
     */
    public function getStatementNumber(): string|null
    {
        return $this->assertNullableString('statement_number');
    }

    /**
     * Currency code.
     */
    public function getCurrency(): string|null
    {
        return $this->assertNullableString('currency');
    }

    /**
     * Safe parser error.
     */
    public function getLastError(): string|null
    {
        return $this->assertNullableString('last_error');
    }

    /**
     * Configured bank name.
     */
    public function getBankName(): string|null
    {
        return $this->assertNullableString('bank_name');
    }

    /**
     * Bank clearing code.
     */
    public function getBankCode(): string|null
    {
        return $this->assertNullableString('bank_code');
    }

    /**
     * Masked account number safe for presentation.
     */
    public function getMaskedAccountNumber(): string|null
    {
        return $this->mask($this->assertNullableString('account_number'));
    }

    /**
     * Masked IBAN safe for presentation.
     */
    public function getMaskedIban(): string|null
    {
        return $this->mask($this->assertNullableString('iban'));
    }

    /**
     * Parsing attempt count.
     */
    public function getAttemptCount(): int
    {
        return $this->assertInt('attempt_count');
    }

    /**
     * Queue timestamp.
     */
    public function getQueuedAt(): Carbon|null
    {
        return $this->assertNullableCarbon('queued_at');
    }

    /**
     * Parse completion timestamp.
     */
    public function getParsedAt(): Carbon|null
    {
        return $this->assertNullableCarbon('parsed_at');
    }

    /**
     * Confirmation timestamp.
     */
    public function getConfirmedAt(): Carbon|null
    {
        return $this->assertNullableCarbon('confirmed_at');
    }

    /**
     * Integrity warning keys.
     *
     * @return list<string>
     */
    public function getParseWarnings(): array
    {
        $warnings = Typer::assertArray($this->getAttribute('parse_warnings') ?? []);

        return \array_values(\array_map(static fn(mixed $warning): string => Typer::assertString($warning), $warnings));
    }

    /**
     * Opening balance as a decimal string.
     */
    public function getOpeningBalance(): string|null
    {
        return $this->nullableDecimal('opening_balance');
    }

    /**
     * Total credits as a decimal string.
     */
    public function getTotalCredits(): string|null
    {
        return $this->nullableDecimal('total_credits');
    }

    /**
     * Total debits as a positive decimal string.
     */
    public function getTotalDebits(): string|null
    {
        return $this->nullableDecimal('total_debits');
    }

    /**
     * Closing balance as a decimal string.
     */
    public function getClosingBalance(): string|null
    {
        return $this->nullableDecimal('closing_balance');
    }

    /**
     * Expected credit count.
     */
    public function getCreditCount(): int|null
    {
        return Typer::parseNullableInt($this->getAttribute('credit_count'));
    }

    /**
     * Expected debit count.
     */
    public function getDebitCount(): int|null
    {
        return Typer::parseNullableInt($this->getAttribute('debit_count'));
    }

    /**
     * Model casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_name' => 'encrypted',
            'account_number' => 'encrypted',
            'iban' => 'encrypted',
            'opening_balance' => 'decimal:2',
            'total_credits' => 'decimal:2',
            'total_debits' => 'decimal:2',
            'closing_balance' => 'decimal:2',
            'available_balance' => 'decimal:2',
            'parse_warnings' => 'encrypted:array',
            'raw_ai_response' => 'encrypted:array',
            'period_from' => 'date',
            'period_to' => 'date',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'parsed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    /**
     * Normalize a nullable decimal attribute.
     */
    private function nullableDecimal(string $key): string|null
    {
        $value = $this->getAttribute($key);

        return $value === null ? null : Typer::assertString($value);
    }

    /**
     * Hide all but the last four alphanumeric account characters.
     */
    private function mask(string|null $value): string|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        $visible = \mb_substr($value, -4);

        return '•••• ' . $visible;
    }
}
