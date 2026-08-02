<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Database\Factories\RecipeCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Thinkycz\LaravelCore\Models\BaseModel;

class RecipeCategory extends BaseModel
{
    use BelongsToUser;
    /** @use HasFactory<RecipeCategoryFactory> */
    use HasFactory;

    protected $table = 'recipe_categories';

    /**
     * @param Builder<RecipeCategory> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where('name', 'like', '%' . $search . '%');
    }

    /**
     * @param Builder<RecipeCategory> $query
     *
     * @return Builder<RecipeCategory>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'user_id', 'name', 'position', 'created_at', 'updated_at']);
    }

    /**
     * @return HasMany<Recipe, $this>
     */
    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class, 'recipe_category_id');
    }

    /**
     * Get the category name.
     */
    public function getName(): string { return $this->assertString('name'); }

    /**
     * Get the owning company id.
     */
    public function getUserId(): int { return $this->assertInt('user_id'); }

    /**
     * Get the display position.
     */
    public function getPosition(): int { return $this->assertInt('position'); }

    /**
     * @return array<string, string>
     */
    protected function casts(): array { return []; }
}
