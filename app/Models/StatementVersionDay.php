<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\StatementVersionDayFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class StatementVersionDay extends BaseModel
{
    /** @use HasFactory<StatementVersionDayFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'statement_version_days';

    /**
     * Scope a search to nothing.
     *
     * @param Builder<StatementVersionDay> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        // No-op: rows are looked up through the parent version.
    }

    /**
     * Restrict the query to a curated set of columns for list views.
     *
     * @param Builder<StatementVersionDay> $query
     *
     * @return Builder<StatementVersionDay>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select([
            'id',
            'version_id',
            'date',
            'cash',
            'card',
            'wolt',
            'bolt',
            'bolt_cash',
            'foodora',
            'total',
            'created_at',
            'updated_at',
        ]);
    }

    /**
     * Version relationship.
     *
     * @return BelongsTo<StatementVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(StatementVersion::class, 'version_id');
    }

    /**
     * Loaded or queried parent version.
     */
    public function getVersion(): StatementVersion
    {
        if ($this->relationLoaded('version')) {
            return $this->assertRelationship('version', StatementVersion::class);
        }

        return Typer::assertInstance($this->version()->first(), StatementVersion::class);
    }

    /**
     * Version id getter.
     */
    public function getVersionId(): int
    {
        return $this->assertInt('version_id');
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
     * Total getter.
     */
    public function getTotal(): float
    {
        return (float) Typer::assertString($this->getAttribute('total'));
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
        ];
    }
}
