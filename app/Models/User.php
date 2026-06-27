<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StoreStatusEnum;
use App\Http\Resources\UserResource;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Support\Carbon;
use RuntimeException;
use Thinkycz\LaravelCore\Models\BaseUser;

class User extends BaseUser implements MustVerifyEmail
{
    /**
     * Scope a query to admin users only.
     *
     * @param Builder<User> $query
     */
    public static function scopeAdmin(Builder $query): void
    {
        $query->where('is_admin', true);
    }

    /**
     * Scope a query to limited (non-admin) users only.
     *
     * @param Builder<User> $query
     */
    public static function scopeLimited(Builder $query): void
    {
        $query->where('is_admin', false);
    }

    /**
     * Scope a query to users managed by the given admin (the admin
     * themselves plus their limited users).
     *
     * @param Builder<User> $query
     */
    public static function scopeForAdmin(Builder $query, self $admin): void
    {
        $query->where(static function (Builder $query) use ($admin): void {
            $query->whereKey($admin->getKey())
                ->orWhere('parent_user_id', $admin->getKey());
        });
    }

    /**
     * Scope a query to users whose assigned store matches the given store.
     *
     * Used by data-access controllers that need to limit rows to the
     * assigned store of a limited user (e.g. inventory counts).
     *
     * @param Builder<User> $query
     */
    public static function scopeForAssignedStore(Builder $query, int $storeId): void
    {
        $query->where('assigned_store_id', $storeId);
    }

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
     * Subordinate (limited) users created by this admin.
     *
     * @return HasMany<User, $this>
     */
    public function subordinateUsers(): HasMany
    {
        return $this->hasMany(self::class, 'parent_user_id');
    }

    /**
     * Store assigned to this limited user.
     *
     * @return BelongsTo<Store, $this>
     */
    public function assignedStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'assigned_store_id');
    }

    /**
     * Ensure this user has at least one warehouse store and return the first.
     *
     * `updateOrCreate` is keyed on `(user_id, is_warehouse = true)`. A
     * unique-key violation fallback is kept for the rare race where two
     * concurrent callers both observe a missing row and try to insert.
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
     * Whether this user is the main admin.
     */
    public function isAdmin(): bool
    {
        return $this->assertBool('is_admin');
    }

    /**
     * Loaded or queried assigned store (may be null for admins).
     */
    public function getAssignedStore(): Store|null
    {
        if ($this->relationLoaded('assignedStore')) {
            return $this->assertNullableRelation('assignedStore', Store::class);
        }

        return $this->assignedStore()->first();
    }

    /**
     * Assigned store id getter.
     */
    public function getAssignedStoreId(): int|null
    {
        return $this->assertNullableInt('assigned_store_id');
    }

    /**
     * Active store id getter (the admin's last-selected store).
     */
    public function getActiveStoreId(): int|null
    {
        return $this->assertNullableInt('active_store_id');
    }

    /**
     * Persist the admin's active store choice onto the model.
     */
    public function setActiveStoreId(int $storeId): void
    {
        $this->setAttribute('active_store_id', $storeId);
        $this->save();
    }

    /**
     * Parent user id getter.
     */
    public function getParentUserId(): int|null
    {
        return $this->assertNullableInt('parent_user_id');
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
            'is_admin' => 'boolean',
        ];
    }
}
