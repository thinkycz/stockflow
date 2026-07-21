<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AttendanceBreakFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\BaseModel;

class AttendanceBreak extends BaseModel
{
    /** @use HasFactory<AttendanceBreakFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'attendance_breaks';

    /**
     * @param Builder<AttendanceBreak> $query
     */
    public static function scopeSearch(Builder $query, string $search): void { $query->where('started_at', 'like', '%' . $search . '%'); }

    /**
     * @param Builder<AttendanceBreak> $query
     *
     * @return Builder<AttendanceBreak>
     */
    public static function querySelect(Builder $query): Builder { return $query->select(['id', 'attendance_session_id', 'created_by_user_id', 'active_session_id', 'started_at', 'ended_at', 'created_at', 'updated_at']); }

    /**
     * Attendance session id getter.
     */
    public function getAttendanceSessionId(): int { return $this->assertInt('attendance_session_id'); }

    /**
     * Break start instant getter.
     */
    public function getStartedAt(): Carbon { return $this->assertCarbon('started_at'); }

    /**
     * Break end instant getter.
     */
    public function getEndedAt(): Carbon|null { return $this->assertNullableCarbon('ended_at'); }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array { return ['started_at' => 'datetime', 'ended_at' => 'datetime']; }
}
