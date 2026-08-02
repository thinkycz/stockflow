<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Database\Factories\RecipeTestAttemptFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class RecipeTestAttempt extends BaseModel
{
    use BelongsToUser;
    /** @use HasFactory<RecipeTestAttemptFactory> */
    use HasFactory;

    protected $table = 'recipe_test_attempts';

    /**
     * @param Builder<RecipeTestAttempt> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where(static function (Builder $query) use ($search): void {
            $query->where('recipe_name', 'like', '%' . $search . '%')->orWhere('worker_name', 'like', '%' . $search . '%');
        });
    }

    /**
     * @param Builder<RecipeTestAttempt> $query
     *
     * @return Builder<RecipeTestAttempt>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'recipe_test_session_id', 'session_position', 'user_id', 'recipe_id', 'recipe_variant_id', 'worker_id', 'actor_user_id', 'recipe_name', 'variant_name', 'worker_name', 'actor_name', 'correct_steps', 'variant_snapshot', 'presented_tokens', 'submitted_tokens', 'submitted_amounts', 'score', 'order_score', 'amount_score', 'passed', 'started_at', 'submitted_at', 'created_at', 'updated_at']);
    }

    /**
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo { return $this->belongsTo(Recipe::class, 'recipe_id'); }

    /**
     * @return BelongsTo<RecipeVariant, $this>
     */
    public function variant(): BelongsTo { return $this->belongsTo(RecipeVariant::class, 'recipe_variant_id'); }

    /**
     * @return BelongsTo<Worker, $this>
     */
    public function worker(): BelongsTo { return $this->belongsTo(Worker::class, 'worker_id'); }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_user_id'); }

    /**
     * @return BelongsTo<RecipeTestSession, $this>
     */
    public function session(): BelongsTo { return $this->belongsTo(RecipeTestSession::class, 'recipe_test_session_id'); }

    /**
     * Get the source recipe id.
     */
    public function getRecipeId(): int|null { return $this->assertNullableInt('recipe_id'); }

    /**
     * Get the owning company id.
     */
    public function getUserId(): int { return $this->assertInt('user_id'); }

    /**
     * Get the selected variant id when it still exists.
     */
    public function getVariantId(): int|null { return $this->assertNullableInt('recipe_variant_id'); }

    /**
     * Get the assessed worker id when it still exists.
     */
    public function getWorkerId(): int|null { return $this->assertNullableInt('worker_id'); }

    /**
     * Get the audit account id when it still exists.
     */
    public function getActorUserId(): int|null { return $this->assertNullableInt('actor_user_id'); }

    /**
     * Get the snapshotted recipe name.
     */
    public function getRecipeName(): string { return $this->assertString('recipe_name'); }

    /**
     * Get the snapshotted variant name.
     */
    public function getVariantName(): string|null { return $this->assertNullableString('variant_name'); }

    /**
     * Get the parent session id for new tests.
     */
    public function getSessionId(): int|null { return $this->assertNullableInt('recipe_test_session_id'); }

    /**
     * Get the position inside a three-recipe session.
     */
    public function getSessionPosition(): int|null { return $this->assertNullableInt('session_position'); }

    /**
     * Get the snapshotted worker name.
     */
    public function getWorkerName(): string { return $this->assertString('worker_name'); }

    /**
     * Get the snapshotted audit account name.
     */
    public function getActorName(): string { return $this->assertString('actor_name'); }

    /**
     * @return list<array{token: string, text: string}>
     */
    public function getCorrectStepsSnapshot(): array
    {
        $steps = [];
        foreach (Typer::assertArray($this->getAttribute('correct_steps')) as $value) {
            $row = Typer::assertStringKeyArray(Typer::assertArray($value));
            $steps[] = ['token' => Typer::assertString($row['token'] ?? null), 'text' => Typer::assertString($row['text'] ?? null)];
        }

        return $steps;
    }

    /**
     * Get the immutable structured variant snapshot for new attempts.
     *
     * Legacy attempts intentionally return null and continue using correct_steps.
     *
     * @return array<string, mixed>|null
     */
    public function getVariantSnapshot(): array|null
    {
        $value = $this->getAttribute('variant_snapshot');

        return $value === null ? null : Typer::assertStringKeyArray(Typer::assertArray($value));
    }

    /**
     * @return list<string>
     */
    public function getPresentedTokens(): array { return \array_values(Typer::assertStringArray(Typer::assertArray($this->getAttribute('presented_tokens')))); }

    /**
     * @return list<string>|null
     */
    public function getSubmittedTokens(): array|null
    {
        $value = $this->getAttribute('submitted_tokens');

        return $value === null ? null : \array_values(Typer::assertStringArray(Typer::assertArray($value)));
    }

    /**
     * Get normalized submitted amount answers.
     *
     * @return array<string, string>|null
     */
    public function getSubmittedAmounts(): array|null
    {
        $value = $this->getAttribute('submitted_amounts');
        if ($value === null) {
            return null;
        }
        $amounts = [];
        foreach (Typer::assertArray($value) as $token => $amount) {
            $amounts[Typer::assertString($token)] = Typer::assertString($amount);
        }

        return $amounts;
    }

    /**
     * Get the percentage score after submission.
     */
    public function getScore(): int|null { return $this->assertNullableInt('score'); }

    /**
     * Get the order-only score.
     */
    public function getOrderScore(): int|null { return $this->assertNullableInt('order_score'); }

    /**
     * Get the amount-only score.
     */
    public function getAmountScore(): int|null { return $this->assertNullableInt('amount_score'); }

    /**
     * Determine whether the submitted attempt passed.
     */
    public function isPassed(): bool { return $this->assertBool('passed'); }

    /**
     * Get the attempt start time.
     */
    public function getStartedAt(): Carbon { return $this->assertCarbon('started_at'); }

    /**
     * Get the submission time.
     */
    public function getSubmittedAt(): Carbon|null { return $this->assertNullableCarbon('submitted_at'); }

    /**
     * @inheritDoc
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
        return [
            'correct_steps' => 'array', 'variant_snapshot' => 'array', 'presented_tokens' => 'array', 'submitted_tokens' => 'array', 'submitted_amounts' => 'array',
            'passed' => 'boolean', 'started_at' => 'datetime', 'submitted_at' => 'datetime',
        ];
    }
}
