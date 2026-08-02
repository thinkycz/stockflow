<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\BaseModel;

class Recipe extends BaseModel
{
    use BelongsToUser;
    /** @use HasFactory<RecipeFactory> */
    use HasFactory;

    protected $table = 'recipes';

    /**
     * @param Builder<Recipe> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where(static function (Builder $query) use ($search): void {
            $query->where('name', 'like', '%' . $search . '%')->orWhere('note', 'like', '%' . $search . '%');
        });
    }

    /**
     * @param Builder<Recipe> $query
     *
     * @return Builder<Recipe>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'user_id', 'recipe_category_id', 'name', 'note', 'position', 'archived_at', 'created_at', 'updated_at']);
    }

    /**
     * @return BelongsTo<RecipeCategory, $this>
     */
    public function category(): BelongsTo { return $this->belongsTo(RecipeCategory::class, 'recipe_category_id'); }

    /**
     * @return HasMany<RecipeVariant, $this>
     */
    public function variants(): HasMany { return $this->hasMany(RecipeVariant::class, 'recipe_id')->orderBy('position')->orderBy('id'); }

    /**
     * @return HasManyThrough<RecipeStep, RecipeVariant, $this>
     */
    public function steps(): HasManyThrough { return $this->hasManyThrough(RecipeStep::class, RecipeVariant::class, 'recipe_id', 'recipe_variant_id'); }

    /**
     * @return HasMany<RecipeTestAttempt, $this>
     */
    public function attempts(): HasMany { return $this->hasMany(RecipeTestAttempt::class, 'recipe_id'); }

    /**
     * Get the recipe category id.
     */
    public function getCategoryId(): int { return $this->assertInt('recipe_category_id'); }

    /**
     * Get the owning company id.
     */
    public function getUserId(): int { return $this->assertInt('user_id'); }

    /**
     * Get the recipe name.
     */
    public function getName(): string { return $this->assertString('name'); }

    /**
     * Get the shared recipe note.
     */
    public function getNote(): string|null { return $this->assertNullableString('note'); }

    /**
     * Get the display position.
     */
    public function getPosition(): int { return $this->assertInt('position'); }

    /**
     * Get the archive timestamp.
     */
    public function getArchivedAt(): Carbon|null { return $this->assertNullableCarbon('archived_at'); }

    /**
     * Determine whether the recipe is archived.
     */
    public function isArchived(): bool { return $this->getArchivedAt() instanceof Carbon; }

    /**
     * Get the recipe category.
     */
    public function getCategory(): RecipeCategory
    {
        if ($this->relationLoaded('category')) {
            return $this->assertRelationship('category', RecipeCategory::class);
        }

        return $this->category()->firstOrFail();
    }

    /**
     * @return Collection<array-key, RecipeVariant>
     */
    public function getVariants(): Collection
    {
        if ($this->relationLoaded('variants')) {
            return $this->assertRelationshipCollection('variants', RecipeVariant::class);
        }

        return $this->variants()->get();
    }

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
    protected function casts(): array { return ['archived_at' => 'datetime']; }
}
