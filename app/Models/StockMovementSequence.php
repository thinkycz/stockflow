<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementTypeEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

/**
 * @property int $user_id
 * @property string $type
 * @property int $year
 * @property int $last_number
 */
class StockMovementSequence extends BaseModel
{
    use HasFactory;

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
     *
     * @var array<int, string>
     */
    protected $primaryKey = ['user_id', 'type', 'year'];

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
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['user_id', 'type', 'year', 'last_number']);
    }

    /**
     * Generate the next number for a given movement type and user, inside a row lock.
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

            $created = StockMovementSequence::query()->create([
                'user_id' => $userId,
                'type' => $type->value,
                'year' => $year,
                'last_number' => 1,
            ]);

            return $created;
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
        $keys = (array) $this->getKeyName();

        foreach ($keys as $key) {
            $query->where($key, '=', $this->getAttribute($key));
        }

        return $query;
    }
}
