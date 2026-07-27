<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use LogicException;
use Thinkycz\LaravelCore\Models\BaseModel;

class AttendanceAudit extends BaseModel
{
    /**
     * The table associated with the model.
     */
    protected $table = 'attendance_audits';

    /**
     * @param Builder<AttendanceAudit> $query
     */
    public static function scopeSearch(Builder $query, string $search): void { $query->where('action', 'like', '%' . $search . '%'); }

    /**
     * @param Builder<AttendanceAudit> $query
     *
     * @return Builder<AttendanceAudit>
     */
    public static function querySelect(Builder $query): Builder { return $query->select(['id', 'attendance_session_id', 'actor_user_id', 'action', 'reason', 'before_state', 'after_state', 'created_at', 'updated_at']); }

    /**
     * Persist a new audit record and reject changes to an existing one.
     *
     * @param array<string, mixed> $options
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Attendance audit records are immutable.');
        }

        return parent::save($options);
    }

    /**
     * Reject deletion of an immutable audit record.
     */
    public function delete(): bool
    {
        throw new LogicException('Attendance audit records are immutable.');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array { return ['before_state' => 'array', 'after_state' => 'array']; }
}
