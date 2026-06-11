<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Model
 */
trait BelongsToUser
{
    /**
     * Boot the trait.
     */
    public static function bootBelongsToUser(): void
    {
        static::creating(static function (Model $model): void {
            if ($model->getAttribute('user_id') !== null) {
                return;
            }

            $user = User::auth();

            if ($user instanceof User) {
                $model->setAttribute('user_id', $user->getKey());
            }
        });
    }

    /**
     * Scope records to the given user.
     *
     * @param Builder<static> $query
     */
    public static function scopeForUser(Builder $query, int|User $user): void
    {
        $userId = $user instanceof User ? $user->getKey() : $user;

        $query->where($query->getModel()->getTable() . '.user_id', $userId);
    }

    /**
     * User relationship.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @inheritDoc
     */
    public function resolveRouteBinding($value, $field = null): Model|null
    {
        $user = User::mustAuth();

        return static::query()
            ->forUser($user)
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }
}
