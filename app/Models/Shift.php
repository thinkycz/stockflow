<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Database\Factories\ShiftFactory;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use LogicException;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class Shift extends BaseModel
{
    use BelongsToUser;
    /** @use HasFactory<ShiftFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'shifts';

    /**
     * Scope a query to only include shifts matching the search term.
     *
     * Matches against the shift date.
     *
     * @param Builder<Shift> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where('date', 'like', '%' . $search . '%');
    }

    /**
     * Scope a query to shifts for the given store.
     *
     * @param Builder<Shift> $query
     */
    public static function scopeForStore(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    /**
     * Scope a query to shifts within the given month.
     *
     * @param Builder<Shift> $query
     */
    public static function scopeForMonth(Builder $query, int $year, int $month): void
    {
        $query->whereYear('date', $year)
            ->whereMonth('date', $month);
    }

    /**
     * Scope a query to shifts for the given worker.
     *
     * @param Builder<Shift> $query
     */
    public static function scopeForWorker(Builder $query, int $workerId): void
    {
        $query->where('worker_id', $workerId);
    }

    /**
     * Restrict the query to a curated set of columns for list views.
     *
     * @param Builder<Shift> $query
     *
     * @return Builder<Shift>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'user_id', 'store_id', 'worker_id', 'date', 'start_time', 'end_time', 'hourly_rate', 'created_at', 'updated_at']);
    }

    /**
     * User id getter.
     */
    public function getUserId(): int
    {
        return $this->assertInt('user_id');
    }

    /**
     * Store id getter.
     */
    public function getStoreId(): int
    {
        return $this->assertInt('store_id');
    }

    /**
     * Worker id getter.
     */
    public function getWorkerId(): int
    {
        return $this->assertInt('worker_id');
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
     * Start time getter (H:i:s).
     */
    public function getStartTime(): string
    {
        return Typer::assertString($this->getAttribute('start_time'));
    }

    /**
     * End time getter (H:i:s).
     */
    public function getEndTime(): string
    {
        return Typer::assertString($this->getAttribute('end_time'));
    }

    /**
     * Start time formatted as H:i (without seconds).
     */
    public function getStartTimeShort(): string
    {
        return \mb_substr($this->getStartTime(), 0, 5);
    }

    /**
     * End time formatted as H:i (without seconds).
     */
    public function getEndTimeShort(): string
    {
        return \mb_substr($this->getEndTime(), 0, 5);
    }

    /**
     * Duration of the shift in minutes.
     */
    public function getDurationMinutes(): int
    {
        $startTime = $this->getStartTime();
        $endTime = $this->getEndTime();
        $start = DateTimeImmutable::createFromFormat(
            \mb_strlen($startTime) === 5 ? '!H:i' : '!H:i:s',
            $startTime,
        );
        $end = DateTimeImmutable::createFromFormat(
            \mb_strlen($endTime) === 5 ? '!H:i' : '!H:i:s',
            $endTime,
        );

        if (!$start instanceof DateTimeImmutable || !$end instanceof DateTimeImmutable) {
            throw new LogicException('Shift contains an invalid stored time.');
        }

        return (int) (($end->getTimestamp() - $start->getTimestamp()) / 60);
    }

    /**
     * Hourly rate snapshotted when the shift was assigned.
     */
    public function getHourlyRate(): float
    {
        return (float) $this->getHourlyRateDecimal();
    }

    /**
     * Snapshotted hourly rate without floating-point conversion.
     */
    public function getHourlyRateDecimal(): string
    {
        return Typer::assertString($this->getAttribute('hourly_rate'));
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
            'hourly_rate' => 'decimal:2',
        ];
    }
}
