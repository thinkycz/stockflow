<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Database\Factories\ShiftPresetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class ShiftPreset extends BaseModel
{
    use BelongsToUser;
    /** @use HasFactory<ShiftPresetFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'shift_presets';

    /**
     * Scope a query to presets matching their name.
     *
     * @param Builder<ShiftPreset> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where('name', 'like', '%' . $search . '%');
    }

    /**
     * Scope presets to one store.
     *
     * @param Builder<ShiftPreset> $query
     */
    public static function scopeForStore(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    /**
     * Restrict a query to the columns used by the application.
     *
     * @param Builder<ShiftPreset> $query
     *
     * @return Builder<ShiftPreset>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'user_id', 'store_id', 'name', 'start_time', 'end_time', 'created_at', 'updated_at']);
    }

    /**
     * Owning user id.
     */
    public function getUserId(): int
    {
        return $this->assertInt('user_id');
    }

    /**
     * Store id.
     */
    public function getStoreId(): int
    {
        return $this->assertInt('store_id');
    }

    /**
     * Display name.
     */
    public function getName(): string
    {
        return $this->assertString('name');
    }

    /**
     * Stored start time.
     */
    public function getStartTime(): string
    {
        return Typer::assertString($this->getAttribute('start_time'));
    }

    /**
     * Stored end time.
     */
    public function getEndTime(): string
    {
        return Typer::assertString($this->getAttribute('end_time'));
    }

    /**
     * Start time formatted as H:i.
     */
    public function getStartTimeShort(): string
    {
        return \mb_substr($this->getStartTime(), 0, 5);
    }

    /**
     * End time formatted as H:i.
     */
    public function getEndTimeShort(): string
    {
        return \mb_substr($this->getEndTime(), 0, 5);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }
}
