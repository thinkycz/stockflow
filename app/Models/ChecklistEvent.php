<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChecklistEventActionEnum;
use Database\Factories\ChecklistEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use LogicException;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class ChecklistEvent extends BaseModel
{
    /** @use HasFactory<ChecklistEventFactory> */
    use HasFactory;

    /**
     * Disable updated timestamps for immutable events.
     */
    public const UPDATED_AT = null;

    /**
     * The table associated with the model.
     */
    protected $table = 'checklist_events';

    /**
     * @param Builder<ChecklistEvent> $query
     */
    public static function scopeSearch(Builder $query, string $search): void { $query->where('reason', 'like', '%' . $search . '%'); }

    /**
     * @param Builder<ChecklistEvent> $query
     *
     * @return Builder<ChecklistEvent>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'checklist_day_id', 'checklist_item_id', 'actor_user_id', 'worker_id', 'action', 'reason', 'created_at']);
    }

    /**
     * Audit action.
     */
    public function getAction(): ChecklistEventActionEnum { return Typer::assertInstance($this->getAttribute('action'), ChecklistEventActionEnum::class); }

    /**
     * @param array<string, mixed> $options
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Checklist events are immutable.');
        }

        return parent::save($options);
    }

    /**
     * Reject event deletion.
     */
    public function delete(): bool
    {
        throw new LogicException('Checklist events are immutable.');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array { return ['action' => ChecklistEventActionEnum::class]; }
}
