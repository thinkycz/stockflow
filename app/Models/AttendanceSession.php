<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Database\Factories\AttendanceSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class AttendanceSession extends BaseModel
{
    use BelongsToUser;
    /** @use HasFactory<AttendanceSessionFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'attendance_sessions';

    /**
     * @param Builder<AttendanceSession> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where('started_at', 'like', '%' . $search . '%');
    }

    /**
     * @param Builder<AttendanceSession> $query
     */
    public static function scopeForStore(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    /**
     * @param Builder<AttendanceSession> $query
     *
     * @return Builder<AttendanceSession>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select([
            'id', 'user_id', 'store_id', 'worker_id', 'shift_id', 'created_by_user_id',
            'active_worker_id', 'scheduled_date', 'scheduled_start_time', 'scheduled_end_time',
            'hourly_rate', 'started_at', 'ended_at', 'voided_at', 'voided_by_user_id',
            'created_at', 'updated_at',
        ]);
    }

    /**
     * @return HasMany<AttendanceBreak, $this>
     */
    public function attendanceBreaks(): HasMany
    {
        return $this->hasMany(AttendanceBreak::class);
    }

    /**
     * @return HasMany<AttendanceAudit, $this>
     */
    public function audits(): HasMany
    {
        return $this->hasMany(AttendanceAudit::class);
    }

    /**
     * Store id getter.
     */
    public function getStoreId(): int { return $this->assertInt('store_id'); }

    /**
     * Worker id getter.
     */
    public function getWorkerId(): int { return $this->assertInt('worker_id'); }

    /**
     * Matched shift id getter.
     */
    public function getShiftId(): int|null { return $this->assertNullableInt('shift_id'); }

    /**
     * Active worker uniqueness key getter.
     */
    public function getActiveWorkerId(): int|null { return $this->assertNullableInt('active_worker_id'); }

    /**
     * Snapshotted hourly rate getter.
     */
    public function getHourlyRate(): float { return (float) Typer::assertString($this->getAttribute('hourly_rate')); }

    /**
     * Arrival instant getter.
     */
    public function getStartedAt(): Carbon { return $this->assertCarbon('started_at'); }

    /**
     * Departure instant getter.
     */
    public function getEndedAt(): Carbon|null { return $this->assertNullableCarbon('ended_at'); }

    /**
     * Void instant getter.
     */
    public function getVoidedAt(): Carbon|null { return $this->assertNullableCarbon('voided_at'); }

    /**
     * Snapshotted shift date getter.
     */
    public function getScheduledDate(): Carbon|null { return $this->assertNullableCarbon('scheduled_date'); }

    /**
     * Snapshotted shift start getter.
     */
    public function getScheduledStartTime(): string|null { return $this->assertNullableString('scheduled_start_time'); }

    /**
     * Snapshotted shift end getter.
     */
    public function getScheduledEndTime(): string|null { return $this->assertNullableString('scheduled_end_time'); }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'hourly_rate' => 'decimal:2',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }
}
