<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementTypeEnum;
use Database\Factories\StockMovementSequenceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class StockMovementSequence extends BaseModel
{
    /** @use HasFactory<StockMovementSequenceFactory> */
    use HasFactory;

    /**
     * Composite key columns used by save queries.
     *
     * @var array<int, string>
     */
    private const array PRIMARY_KEYS = ['user_id', 'type', 'year'];

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The table associated with the model.
     */
    protected $table = 'stock_movement_sequences';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'user_id';

    /**
     * Scope a search to nothing (no text search on this table).
     *
     * @param Builder<StockMovementSequence> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        // No-op: sequences are looked up by (user_id, type, year).
    }

    /**
     * Restrict the query to a curated set of columns for list views.
     *
     * @param Builder<StockMovementSequence> $query
     *
     * @return Builder<StockMovementSequence>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['user_id', 'type', 'year', 'last_number']);
    }

    /**
     * Generate the next number for a given movement type and user, inside a row lock.
     *
     * The (user_id, type, year) row is treated as a single-row counter.
     * Two concurrent first-time callers can both observe a missing row
     * under `lockForUpdate`; the second `create()` then collides on the
     * primary key. We catch the unique-key violation and retry the
     * locked read+update path, which is now guaranteed to find the row
     * the first caller just inserted.
     */
    public static function next(StockMovementTypeEnum $type, int $year, int $userId): string
    {
        $row = DB::transaction(function () use ($type, $year, $userId): StockMovementSequence {
            $existing = static::query()
                ->where('user_id', $userId)
                ->where('type', $type->value)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof StockMovementSequence) {
                return self::bump($type, $year, $userId, $existing);
            }

            try {
                return StockMovementSequence::query()->create([
                    'user_id' => $userId,
                    'type' => $type->value,
                    'year' => $year,
                    'last_number' => 1,
                ]);
            } catch (UniqueConstraintViolationException) {
                $existing = static::query()
                    ->where('user_id', $userId)
                    ->where('type', $type->value)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->first();

                if (!$existing instanceof StockMovementSequence) {
                    throw new RuntimeException('Stock movement sequence race could not be resolved.');
                }

                return self::bump($type, $year, $userId, $existing);
            }
        });

        return \sprintf('%s-%d-%04d', $type->prefix(), $year, $row->getLastNumber());
    }

    /**
     * Type getter.
     */
    public function getType(): string
    {
        return $this->assertString('type');
    }

    /**
     * Year getter.
     */
    public function getYear(): int
    {
        return $this->assertInt('year');
    }

    /**
     * Last number getter.
     */
    public function getLastNumber(): int
    {
        return Typer::assertInt($this->getAttribute('last_number'));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'year' => 'integer',
            'last_number' => 'integer',
        ];
    }

    /**
     * @inheritDoc
     */
    protected function setKeysForSaveQuery($query)
    {
        foreach (self::PRIMARY_KEYS as $key) {
            $query->where($key, '=', $this->getAttribute($key));
        }

        return $query;
    }

    /**
     * Increment and persist the last_number for an existing sequence row.
     */
    private static function bump(StockMovementTypeEnum $type, int $year, int $userId, self $existing): self
    {
        $newNumber = $existing->getLastNumber() + 1;

        static::query()
            ->where('user_id', $userId)
            ->where('type', $type->value)
            ->where('year', $year)
            ->update(['last_number' => $newNumber]);

        $existing->setRawAttributes(
            \array_merge($existing->getAttributes(), ['last_number' => $newNumber]),
            true,
        );

        return $existing;
    }
}
