<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Database\Factories\ShiftRequestFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class ShiftRequest extends BaseModel
{
    use BelongsToUser;
    /** @use HasFactory<ShiftRequestFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'shift_requests';

    /**
     * Scope requests by their date.
     *
     * @param Builder<ShiftRequest> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where('date', 'like', '%' . $search . '%');
    }

    /**
     * Scope requests to one store.
     *
     * @param Builder<ShiftRequest> $query
     */
    public static function scopeForStore(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    /**
     * Scope requests to one calendar month.
     *
     * @param Builder<ShiftRequest> $query
     */
    public static function scopeForMonth(Builder $query, int $year, int $month): void
    {
        $query->whereYear('date', $year)->whereMonth('date', $month);
    }

    /**
     * Scope requests to one worker.
     *
     * @param Builder<ShiftRequest> $query
     */
    public static function scopeForWorker(Builder $query, int $workerId): void
    {
        $query->where('worker_id', $workerId);
    }

    /**
     * @param Builder<ShiftRequest> $query
     *
     * @return Builder<ShiftRequest>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select([
            'id', 'user_id', 'store_id', 'worker_id', 'date', 'start_time',
            'end_time', 'created_at', 'updated_at',
        ]);
    }

    /**
     * Owning company user id.
     */
    public function getUserId(): int { return $this->assertInt('user_id'); }

    /**
     * Store id.
     */
    public function getStoreId(): int { return $this->assertInt('store_id'); }

    /**
     * Worker id.
     */
    public function getWorkerId(): int { return $this->assertInt('worker_id'); }

    /**
     * Requested date formatted as Y-m-d.
     */
    public function getDate(): string
    {
        $value = $this->getAttribute('date');

        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : Typer::assertString($value);
    }

    /**
     * Stored start time.
     */
    public function getStartTime(): string { return Typer::assertString($this->getAttribute('start_time')); }

    /**
     * Stored end time.
     */
    public function getEndTime(): string { return Typer::assertString($this->getAttribute('end_time')); }

    /**
     * Start time formatted as H:i.
     */
    public function getStartTimeShort(): string { return \mb_substr($this->getStartTime(), 0, 5); }

    /**
     * End time formatted as H:i.
     */
    public function getEndTimeShort(): string { return \mb_substr($this->getEndTime(), 0, 5); }

    /**
     * Model casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
