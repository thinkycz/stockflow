<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Database\Factories\ShiftRequestMonthLockFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\BaseModel;

class ShiftRequestMonthLock extends BaseModel
{
    use BelongsToUser;
    /** @use HasFactory<ShiftRequestMonthLockFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'shift_request_month_locks';

    /**
     * Scope locks by their year-month label.
     *
     * @param Builder<ShiftRequestMonthLock> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->whereRaw('CONCAT(year, \'-\', LPAD(month, 2, \'0\')) like ?', ['%' . $search . '%']);
    }

    /**
     * @param Builder<ShiftRequestMonthLock> $query
     *
     * @return Builder<ShiftRequestMonthLock>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select([
            'id', 'user_id', 'store_id', 'year', 'month', 'locked_at',
            'locked_by_user_id', 'created_at', 'updated_at',
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
     * Locked year.
     */
    public function getYear(): int { return $this->assertInt('year'); }

    /**
     * Locked month.
     */
    public function getMonth(): int { return $this->assertInt('month'); }

    /**
     * Latest lock time.
     */
    public function getLockedAt(): Carbon { return $this->assertCarbon('locked_at'); }

    /**
     * Admin who locked the month, when still available.
     */
    public function getLockedByUserId(): int|null { return $this->assertNullableInt('locked_by_user_id'); }

    /**
     * Model casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['year' => 'integer', 'month' => 'integer', 'locked_at' => 'datetime'];
    }
}
