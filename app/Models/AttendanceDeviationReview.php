<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttendanceDeviationReviewDecisionEnum;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use LogicException;
use Thinkycz\LaravelCore\Models\BaseModel;

class AttendanceDeviationReview extends BaseModel
{
    use BelongsToUser;

    /**
     * The table associated with the model.
     */
    protected $table = 'attendance_deviation_reviews';

    /**
     * @param Builder<AttendanceDeviationReview> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where('reason', 'like', '%' . $search . '%');
    }

    /**
     * @param Builder<AttendanceDeviationReview> $query
     *
     * @return Builder<AttendanceDeviationReview>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select([
            'id', 'user_id', 'store_id', 'shift_id', 'actor_user_id', 'decision', 'reason',
            'actual_started_at', 'actual_ended_at', 'before_start_time', 'before_end_time',
            'after_start_time', 'after_end_time', 'created_at', 'updated_at',
        ]);
    }

    /**
     * Decision getter.
     */
    public function getDecision(): AttendanceDeviationReviewDecisionEnum
    {
        return AttendanceDeviationReviewDecisionEnum::from($this->assertString('decision'));
    }

    /**
     * Shift id getter.
     */
    public function getShiftId(): int
    {
        return $this->assertInt('shift_id');
    }

    /**
     * Review reason getter.
     */
    public function getReason(): string
    {
        return $this->assertString('reason');
    }

    /**
     * Actual first arrival getter.
     */
    public function getActualStartedAt(): Carbon
    {
        return $this->assertCarbon('actual_started_at');
    }

    /**
     * Actual last departure getter.
     */
    public function getActualEndedAt(): Carbon
    {
        return $this->assertCarbon('actual_ended_at');
    }

    /**
     * Resulting shift start getter.
     */
    public function getAfterStartTime(): string
    {
        return $this->assertString('after_start_time');
    }

    /**
     * Resulting shift end getter.
     */
    public function getAfterEndTime(): string
    {
        return $this->assertString('after_end_time');
    }

    /**
     * Creation timestamp getter.
     */
    public function getCreatedAt(): Carbon
    {
        return $this->assertCarbon('created_at');
    }

    /**
     * Persist a new audit record and reject later mutations.
     *
     * @param array<string, mixed> $options
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Attendance deviation review records are immutable.');
        }

        return parent::save($options);
    }

    /**
     * Reject deletion of an immutable audit record.
     */
    public function delete(): bool
    {
        throw new LogicException('Attendance deviation review records are immutable.');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actual_started_at' => 'datetime',
            'actual_ended_at' => 'datetime',
        ];
    }
}
