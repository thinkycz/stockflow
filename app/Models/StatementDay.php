<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\StatementDayFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class StatementDay extends BaseModel
{
    /** @use HasFactory<StatementDayFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'statement_days';

    /**
     * Scope a search to nothing (no text search on this table).
     *
     * @param Builder<StatementDay> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        // No-op: rows are looked up through the parent statement.
    }

    /**
     * Restrict the query to a curated set of columns for list views.
     *
     * @param Builder<StatementDay> $query
     *
     * @return Builder<StatementDay>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select([
            'id',
            'statement_id',
            'date',
            'cash',
            'card',
            'wolt',
            'bolt',
            'bolt_cash',
            'foodora',
            'total',
            'cash_checked',
            'created_at',
            'updated_at',
        ]);
    }

    /**
     * Statement relationship.
     *
     * @return BelongsTo<Statement, $this>
     */
    public function statement(): BelongsTo
    {
        return $this->belongsTo(Statement::class, 'statement_id');
    }

    /**
     * Loaded or queried parent statement.
     */
    public function getStatement(): Statement
    {
        if ($this->relationLoaded('statement')) {
            return $this->assertRelationship('statement', Statement::class);
        }

        return Typer::assertInstance($this->statement()->first(), Statement::class);
    }

    /**
     * Statement id getter.
     */
    public function getStatementId(): int
    {
        return $this->assertInt('statement_id');
    }

    /**
     * Date getter (Y-m-d).
     */
    public function getDate(): string
    {
        $value = $this->getAttribute('date');

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return Typer::assertString($value);
    }

    /**
     * Cash getter.
     */
    public function getCash(): float
    {
        return (float) Typer::assertString($this->getAttribute('cash'));
    }

    /**
     * Card getter.
     */
    public function getCard(): float
    {
        return (float) Typer::assertString($this->getAttribute('card'));
    }

    /**
     * Wolt getter.
     */
    public function getWolt(): float
    {
        return (float) Typer::assertString($this->getAttribute('wolt'));
    }

    /**
     * Bolt getter.
     */
    public function getBolt(): float
    {
        return (float) Typer::assertString($this->getAttribute('bolt'));
    }

    /**
     * Bolt cash getter.
     */
    public function getBoltCash(): float
    {
        return (float) Typer::assertString($this->getAttribute('bolt_cash'));
    }

    /**
     * Foodora getter.
     */
    public function getFoodora(): float
    {
        return (float) Typer::assertString($this->getAttribute('foodora'));
    }

    /**
     * Total getter (cash + card + wolt + bolt + bolt_cash + foodora).
     */
    public function getTotal(): float
    {
        return (float) Typer::assertString($this->getAttribute('total'));
    }

    /**
     * Cash checked getter. Indicates an admin confirmed the cash amount
     * matches what was taken home from the store for this day.
     */
    public function getCashChecked(): bool
    {
        return (bool) $this->getAttribute('cash_checked');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'cash' => 'decimal:2',
            'card' => 'decimal:2',
            'wolt' => 'decimal:2',
            'bolt' => 'decimal:2',
            'bolt_cash' => 'decimal:2',
            'foodora' => 'decimal:2',
            'total' => 'decimal:2',
            'cash_checked' => 'boolean',
        ];
    }
}
