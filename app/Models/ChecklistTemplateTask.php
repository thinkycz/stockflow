<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChecklistShiftEnum;
use App\Enums\ChecklistTemplateScopeEnum;
use App\Models\Concerns\BelongsToUser;
use Database\Factories\ChecklistTemplateTaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class ChecklistTemplateTask extends BaseModel
{
    use BelongsToUser;
    /** @use HasFactory<ChecklistTemplateTaskFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'checklist_template_tasks';

    /**
     * @param Builder<ChecklistTemplateTask> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where('text', 'like', '%' . $search . '%');
    }

    /**
     * @param Builder<ChecklistTemplateTask> $query
     *
     * @return Builder<ChecklistTemplateTask>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'user_id', 'store_id', 'scope', 'weekday', 'shift', 'text', 'position', 'created_at', 'updated_at']);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /**
     * Store id.
     */
    public function getStoreId(): int { return $this->assertInt('store_id'); }

    /**
     * Template recurrence scope.
     */
    public function getScope(): ChecklistTemplateScopeEnum { return Typer::assertInstance($this->getAttribute('scope'), ChecklistTemplateScopeEnum::class); }

    /**
     * ISO weekday for weekly tasks.
     */
    public function getWeekday(): int|null { return $this->assertNullableInt('weekday'); }

    /**
     * Shift assignment.
     */
    public function getShift(): ChecklistShiftEnum { return Typer::assertInstance($this->getAttribute('shift'), ChecklistShiftEnum::class); }

    /**
     * Task text.
     */
    public function getText(): string { return $this->assertString('text'); }

    /**
     * Ordered position.
     */
    public function getPosition(): int { return $this->assertInt('position'); }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['scope' => ChecklistTemplateScopeEnum::class, 'shift' => ChecklistShiftEnum::class];
    }
}
