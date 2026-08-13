<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Database\Factories\ShiftShareLinkFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class ShiftShareLink extends BaseModel
{
    use BelongsToUser;
    /** @use HasFactory<ShiftShareLinkFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'shift_share_links';

    /**
     * Scope links matching their display name.
     *
     * @param Builder<ShiftShareLink> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where('name', 'like', '%' . $search . '%');
    }

    /**
     * Scope links to one store.
     *
     * @param Builder<ShiftShareLink> $query
     */
    public static function scopeForStore(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    /**
     * Scope links to one public token.
     *
     * @param Builder<ShiftShareLink> $query
     */
    public static function scopeForToken(Builder $query, string $token): void
    {
        $query->where('token', $token);
    }

    /**
     * Restrict a query to the columns used by the application.
     *
     * @param Builder<ShiftShareLink> $query
     *
     * @return Builder<ShiftShareLink>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'user_id', 'store_id', 'name', 'token', 'created_at', 'updated_at']);
    }

    /**
     * Resolve the store exposed by a live public token.
     */
    public static function findStoreForToken(string $token): Store|null
    {
        $query = self::query();
        self::scopeForToken($query, $token);
        self::querySelect($query);
        $link = $query->with('store')->first();

        return $link instanceof self ? $link->getStore() : null;
    }

    /**
     * Owning store relationship.
     *
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /**
     * Resolve the owning store without dynamic relation access.
     */
    public function getStore(): Store
    {
        if ($this->relationLoaded('store')) {
            return $this->assertRelationship('store', Store::class);
        }

        return Typer::assertInstance($this->store()->first(), Store::class);
    }

    /**
     * Owning user id.
     */
    public function getUserId(): int
    {
        return $this->assertInt('user_id');
    }

    /**
     * Owning store id.
     */
    public function getStoreId(): int
    {
        return $this->assertInt('store_id');
    }

    /**
     * Display name, or null for a migrated legacy link.
     */
    public function getName(): string|null
    {
        return $this->assertNullableString('name');
    }

    /**
     * Unguessable public token.
     */
    public function getToken(): string
    {
        return $this->assertString('token');
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
