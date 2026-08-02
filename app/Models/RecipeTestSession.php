<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\BaseModel;

class RecipeTestSession extends BaseModel
{
    use BelongsToUser;

    /**
     * @var string|null
     */
    protected $table = 'recipe_test_sessions';

    /**
     * @param Builder<RecipeTestSession> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where('worker_name', 'like', '%' . $search . '%');
    }

    /**
     * @param Builder<RecipeTestSession> $query
     *
     * @return Builder<RecipeTestSession>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'user_id', 'worker_id', 'actor_user_id', 'worker_name', 'actor_name', 'score', 'passed', 'started_at', 'submitted_at', 'created_at', 'updated_at']);
    }

    /**
     * @return BelongsTo<Worker, $this>
     */
    public function worker(): BelongsTo { return $this->belongsTo(Worker::class); }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_user_id'); }

    /**
     * @return HasMany<RecipeTestAttempt, $this>
     */
    public function attempts(): HasMany { return $this->hasMany(RecipeTestAttempt::class)->orderBy('session_position'); }

    /**
     * Get ordered child attempts.
     *
     * @return Collection<array-key, RecipeTestAttempt>
     */
    public function getAttempts(): Collection
    {
        if ($this->relationLoaded('attempts')) {
            return $this->assertRelationshipCollection('attempts', RecipeTestAttempt::class);
        }

        return $this->attempts()->get();
    }

    /**
     * Get the owning company id.
     */
    public function getUserId(): int { return $this->assertInt('user_id'); }

    /**
     * Get the worker snapshot.
     */
    public function getWorkerName(): string { return $this->assertString('worker_name'); }

    /**
     * Get the audit actor id.
     */
    public function getActorUserId(): int|null { return $this->assertNullableInt('actor_user_id'); }

    /**
     * Get the combined score.
     */
    public function getScore(): int|null { return $this->assertNullableInt('score'); }

    /**
     * Determine whether the session passed.
     */
    public function isPassed(): bool { return $this->assertBool('passed'); }

    /**
     * Get submission time.
     */
    public function getSubmittedAt(): Carbon|null { return $this->assertNullableCarbon('submitted_at'); }

    /**
     * Resolve only sessions in the authenticated company.
     */
    public function resolveRouteBinding($value, $field = null): Model|null
    {
        return self::query()->where('user_id', User::mustAuth()->resolveScopeUser()->getKey())
            ->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['passed' => 'boolean', 'started_at' => 'datetime', 'submitted_at' => 'datetime'];
    }
}
