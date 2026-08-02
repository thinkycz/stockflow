<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RecipeVariantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Thinkycz\LaravelCore\Models\BaseModel;

class RecipeVariant extends BaseModel
{
    /** @use HasFactory<RecipeVariantFactory> */
    use HasFactory;

    protected $table = 'recipe_variants';

    /**
     * @param Builder<RecipeVariant> $query
     */
    public static function scopeSearch(Builder $query, string $search): void { $query->where('name', 'like', '%' . $search . '%'); }

    /**
     * @param Builder<RecipeVariant> $query
     *
     * @return Builder<RecipeVariant>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'recipe_id', 'name', 'position', 'created_at', 'updated_at']);
    }

    /**
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo { return $this->belongsTo(Recipe::class, 'recipe_id'); }

    /**
     * @return HasMany<RecipeStep, $this>
     */
    public function steps(): HasMany { return $this->hasMany(RecipeStep::class, 'recipe_variant_id')->orderBy('position')->orderBy('id'); }

    /**
     * Ordered structured ingredients belonging to this variant.
     *
     * @return HasMany<RecipeIngredient, $this>
     */
    public function ingredients(): HasMany { return $this->hasMany(RecipeIngredient::class, 'recipe_variant_id')->orderBy('position')->orderBy('id'); }

    /**
     * @return HasMany<RecipeInstruction, $this>
     */
    public function instructions(): HasMany { return $this->hasMany(RecipeInstruction::class, 'recipe_variant_id')->orderBy('position')->orderBy('id'); }

    /**
     * Get the parent recipe id.
     */
    public function getRecipeId(): int { return $this->assertInt('recipe_id'); }

    /**
     * Get the optional variant label.
     */
    public function getName(): string|null { return $this->assertNullableString('name'); }

    /**
     * Get the display position.
     */
    public function getPosition(): int { return $this->assertInt('position'); }

    /**
     * @return Collection<array-key, RecipeStep>
     */
    public function getSteps(): Collection
    {
        if ($this->relationLoaded('steps')) {
            return $this->assertRelationshipCollection('steps', RecipeStep::class);
        }

        return $this->steps()->get();
    }

    /**
     * @return Collection<array-key, RecipeIngredient>
     */
    public function getIngredients(): Collection
    {
        if ($this->relationLoaded('ingredients')) {
            return $this->assertRelationshipCollection('ingredients', RecipeIngredient::class);
        }

        return $this->ingredients()->get();
    }

    /**
     * @return Collection<array-key, RecipeInstruction>
     */
    public function getInstructions(): Collection
    {
        if ($this->relationLoaded('instructions')) {
            return $this->assertRelationshipCollection('instructions', RecipeInstruction::class);
        }

        return $this->instructions()->get();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array { return []; }
}
