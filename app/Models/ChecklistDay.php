<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Database\Factories\ChecklistDayFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\BaseModel;

class ChecklistDay extends BaseModel
{
    use BelongsToUser;
    /** @use HasFactory<ChecklistDayFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'checklist_days';

    /**
     * @param Builder<ChecklistDay> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where('date', 'like', '%' . $search . '%');
    }

    /**
     * @param Builder<ChecklistDay> $query
     *
     * @return Builder<ChecklistDay>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'user_id', 'store_id', 'date', 'excused_by_user_id', 'excuse_reason', 'excused_at', 'created_at', 'updated_at']);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo { return $this->belongsTo(Store::class, 'store_id'); }

    /**
     * @return HasMany<ChecklistItem, $this>
     */
    public function items(): HasMany { return $this->hasMany(ChecklistItem::class, 'checklist_day_id'); }

    /**
     * @return HasMany<ChecklistEvent, $this>
     */
    public function events(): HasMany { return $this->hasMany(ChecklistEvent::class, 'checklist_day_id'); }

    /**
     * Store id.
     */
    public function getStoreId(): int { return $this->assertInt('store_id'); }

    /**
     * Local checklist date.
     */
    public function getDate(): Carbon { return $this->assertCarbon('date'); }

    /**
     * Administrative excuse reason.
     */
    public function getExcuseReason(): string|null { return $this->assertNullableString('excuse_reason'); }

    /**
     * Excuse timestamp.
     */
    public function getExcusedAt(): Carbon|null { return $this->assertNullableCarbon('excused_at'); }

    /**
     * Whether the day is excused.
     */
    public function isExcused(): bool { return $this->getExcusedAt() instanceof Carbon; }

    /**
     * @return Collection<array-key, ChecklistItem>
     */
    public function getItems(): Collection
    {
        if ($this->relationLoaded('items')) {
            return $this->assertRelationshipCollection('items', ChecklistItem::class);
        }

        return $this->items()->get();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array { return ['date' => 'date', 'excused_at' => 'datetime']; }
}
