<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChecklistShiftEnum;
use Database\Factories\ChecklistItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class ChecklistItem extends BaseModel
{
    /** @use HasFactory<ChecklistItemFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'checklist_items';

    /**
     * @param Builder<ChecklistItem> $query
     */
    public static function scopeSearch(Builder $query, string $search): void { $query->where('text', 'like', '%' . $search . '%'); }

    /**
     * @param Builder<ChecklistItem> $query
     *
     * @return Builder<ChecklistItem>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'checklist_day_id', 'template_task_id', 'shift', 'text', 'position', 'completed_by_worker_id', 'completed_by_user_id', 'completed_at', 'lock_version', 'created_at', 'updated_at']);
    }

    /**
     * @return BelongsTo<ChecklistDay, $this>
     */
    public function day(): BelongsTo { return $this->belongsTo(ChecklistDay::class, 'checklist_day_id'); }

    /**
     * @return BelongsTo<Worker, $this>
     */
    public function completedByWorker(): BelongsTo { return $this->belongsTo(Worker::class, 'completed_by_worker_id'); }

    /**
     * Day id.
     */
    public function getChecklistDayId(): int { return $this->assertInt('checklist_day_id'); }

    /**
     * Shift assignment.
     */
    public function getShift(): ChecklistShiftEnum { return Typer::assertInstance($this->getAttribute('shift'), ChecklistShiftEnum::class); }

    /**
     * Snapshotted task text.
     */
    public function getText(): string { return $this->assertString('text'); }

    /**
     * Position within the shift.
     */
    public function getPosition(): int { return $this->assertInt('position'); }

    /**
     * Completing worker id.
     */
    public function getCompletedByWorkerId(): int|null { return $this->assertNullableInt('completed_by_worker_id'); }

    /**
     * Completion timestamp.
     */
    public function getCompletedAt(): Carbon|null { return $this->assertNullableCarbon('completed_at'); }

    /**
     * Optimistic lock version.
     */
    public function getLockVersion(): int { return $this->assertInt('lock_version'); }

    /**
     * Whether the item is completed.
     */
    public function isCompleted(): bool { return $this->getCompletedAt() instanceof Carbon; }

    /**
     * Loaded completing worker.
     */
    public function getCompletedByWorker(): Worker|null { return $this->assertNullableRelation('completedByWorker', Worker::class); }

    /**
     * @return array<string, string>
     */
    protected function casts(): array { return ['shift' => ChecklistShiftEnum::class, 'completed_at' => 'datetime']; }
}
