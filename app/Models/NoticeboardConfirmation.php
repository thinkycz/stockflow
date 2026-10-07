<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\BaseModel;

class NoticeboardConfirmation extends BaseModel
{
    use BelongsToUser;

    /**
     * @param Builder<NoticeboardConfirmation> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where('worker_name', 'like', '%' . $search . '%');
    }

    /**
     * @param Builder<NoticeboardConfirmation> $query
     */
    public static function scopeForStore(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    /**
     * @param Builder<NoticeboardConfirmation> $query
     *
     * @return Builder<NoticeboardConfirmation>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'user_id', 'store_id', 'attendance_session_id', 'worker_id', 'worker_name', 'date', 'confirmed_by_user_id', 'confirmed_at', 'created_at', 'updated_at']);
    }

    /**
     * @return HasMany<NoticeboardConfirmationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(NoticeboardConfirmationItem::class, 'confirmation_id')->orderBy('position');
    }

    /**
     * @return Collection<array-key, NoticeboardConfirmationItem>
     */
    public function getItems(): Collection
    {
        return $this->relationLoaded('items')
            ? $this->assertRelationshipCollection('items', NoticeboardConfirmationItem::class)
            : $this->items()->get();
    }

    /**
     * Company owner.
     */
    public function getUserId(): int { return $this->assertInt('user_id'); }

    /**
     * Owning store.
     */
    public function getStoreId(): int { return $this->assertInt('store_id'); }

    /**
     * First arriving worker.
     */
    public function getWorkerId(): int { return $this->assertInt('worker_id'); }

    /**
     * Worker name at the first arrival.
     */
    public function getWorkerName(): string { return $this->assertString('worker_name'); }

    /**
     * First arrival session.
     */
    public function getAttendanceSessionId(): int { return $this->assertInt('attendance_session_id'); }

    /**
     * Business day.
     */
    public function getDate(): string { return $this->assertString('date'); }

    /**
     * Account that submitted the confirmation.
     */
    public function getConfirmedByUserId(): int|null { return $this->assertNullableInt('confirmed_by_user_id'); }

    /**
     * Confirmation instant.
     */
    public function getConfirmedAt(): Carbon|null { return $this->assertNullableCarbon('confirmed_at'); }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime'];
    }
}
