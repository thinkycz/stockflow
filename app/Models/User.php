<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StoreStatusEnum;
use App\Http\Resources\UserResource;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Support\Carbon;
use RuntimeException;
use Thinkycz\LaravelCore\Models\BaseUser;

class User extends BaseUser implements MustVerifyEmail
{
    /**
     * Stores owned by this user.
     *
     * @return HasMany<Store, $this>
     */
    public function stores(): HasMany
    {
        return $this->hasMany(Store::class, 'user_id');
    }

    /**
     * Items owned by this user.
     *
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'user_id');
    }

    /**
     * Stock movements owned by this user.
     *
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'user_id');
    }

    /**
     * Ensure this user has at least one warehouse store and return the first.
     *
     * `updateOrCreate` is keyed on `(user_id, is_warehouse = true)`. The
     * `(user_id, is_warehouse)` unique constraint added in
     * `2026_06_10_000010_enforce_single_warehouse_per_user` guarantees
     * that two concurrent callers cannot both insert a fresh row. If a
     * unique-key violation still leaks through (e.g. the constraint is
     * removed in a future schema change), the second caller falls back
     * to the existing row.
     */
    public function provisionWarehouse(): Store
    {
        $warehouse = $this->stores()->where('is_warehouse', true)->first();

        if ($warehouse instanceof Store) {
            return $warehouse;
        }

        try {
            return Store::query()->updateOrCreate(
                [
                    'user_id' => $this->getKey(),
                    'is_warehouse' => true,
                ],
                [
                    'name' => 'Warehouse',
                    'status' => StoreStatusEnum::ACTIVE->value,
                ],
            );
        } catch (UniqueConstraintViolationException) {
            $existing = $this->stores()->where('is_warehouse', true)->first();

            if ($existing instanceof Store) {
                return $existing;
            }

            throw new RuntimeException('Warehouse provisioning race could not be resolved.');
        }
    }

    /**
     * Resolve the default warehouse store for this user.
     */
    public function warehouse(): Store
    {
        return $this->provisionWarehouse();
    }

    /**
     * Email getter.
     */
    public function getEmail(): string
    {
        return $this->assertString('email');
    }

    /**
     * Locale getter.
     */
    public function getLocale(): string
    {
        return $this->assertString('locale');
    }

    /**
     * EmailVerifiedAt getter.
     */
    public function getEmailVerifiedAt(): Carbon|null
    {
        return $this->assertNullableCarbon('email_verified_at');
    }

    /**
     * Me resource.
     */
    public function meResource(): JsonApiResource
    {
        return new UserResource($this);
    }

    /**
     * VND json:api resource.
     */
    public function resource(): JsonApiResource
    {
        return $this->meResource();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
