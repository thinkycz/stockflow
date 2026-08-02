<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\RecipeStep;
use App\Models\RecipeVariant;
use App\Models\User;
use App\Support\RecipeDefaultCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Typer;

class RecipeCatalogService
{
    /**
     * Initialize the source PDF catalog exactly once for a company owner.
     */
    public function initialize(User $owner): void
    {
        DB::transaction(static function () use ($owner): void {
            $locked = Typer::assertInstance(User::query()->whereKey($owner->getKey())->lockForUpdate()->firstOrFail(), User::class);
            if ($locked->getRecipesInitializedAt() !== null) {
                return;
            }

            foreach (RecipeDefaultCatalog::categories() as $categoryPosition => $categoryRow) {
                $category = Typer::assertInstance(RecipeCategory::query()->create([
                    'user_id' => $locked->getKey(),
                    'name' => $categoryRow['name'],
                    'position' => $categoryPosition + 1,
                ]), RecipeCategory::class);

                foreach ($categoryRow['recipes'] as $recipePosition => $recipeRow) {
                    $recipe = Typer::assertInstance(Recipe::query()->create([
                        'user_id' => $locked->getKey(),
                        'recipe_category_id' => $category->getKey(),
                        'name' => $recipeRow['name'],
                        'note' => $recipeRow['note'],
                        'position' => $recipePosition + 1,
                        'archived_at' => null,
                    ]), Recipe::class);

                    foreach ($recipeRow['variants'] as $variantPosition => $variantRow) {
                        $variant = Typer::assertInstance(RecipeVariant::query()->create([
                            'recipe_id' => $recipe->getKey(),
                            'name' => $variantRow['name'],
                            'position' => $variantPosition + 1,
                        ]), RecipeVariant::class);
                        foreach ($variantRow['steps'] as $stepPosition => $text) {
                            RecipeStep::query()->create([
                                'recipe_variant_id' => $variant->getKey(),
                                'text' => $text,
                                'position' => $stepPosition + 1,
                            ]);
                        }
                    }
                }
            }

            $locked->setAttribute('recipes_initialized_at', Carbon::now());
            $locked->save();
        });
    }

    /**
     * @param list<array{name: string|null, steps: list<string>}> $variants
     */
    public function save(User $owner, RecipeCategory $category, Recipe|null $recipe, string $name, string|null $note, array $variants): Recipe
    {
        if ($category->getUserId() !== $owner->getKey() || ($recipe instanceof Recipe && $recipe->getUserId() !== $owner->getKey())) {
            throw new InvalidArgumentException('Recipe does not belong to this company.');
        }
        if ($variants === []) {
            throw new InvalidArgumentException('Recipe must contain a variant.');
        }
        foreach ($variants as $variant) {
            if (\count($variant['steps']) < 2) {
                throw new InvalidArgumentException('Every recipe variant must contain at least two steps.');
            }
        }

        return DB::transaction(static function () use ($owner, $category, $recipe, $name, $note, $variants): Recipe {
            $target = $recipe instanceof Recipe
                ? Typer::assertInstance(Recipe::query()->whereKey($recipe->getKey())->where('user_id', $owner->getKey())->lockForUpdate()->firstOrFail(), Recipe::class)
                : new Recipe();
            $target->setAttribute('user_id', $owner->getKey());
            $target->setAttribute('recipe_category_id', $category->getKey());
            $target->setAttribute('name', $name);
            $target->setAttribute('note', $note);
            if (!$recipe instanceof Recipe) {
                $lastPosition = Typer::assertNullableInt(Recipe::query()->where('recipe_category_id', $category->getKey())->max('position'));
                $target->setAttribute('position', ($lastPosition ?? 0) + 1);
                $target->setAttribute('archived_at', null);
            }
            $target->save();
            $target->variants()->delete();

            foreach ($variants as $variantPosition => $variantRow) {
                $variant = Typer::assertInstance(RecipeVariant::query()->create([
                    'recipe_id' => $target->getKey(), 'name' => $variantRow['name'], 'position' => $variantPosition + 1,
                ]), RecipeVariant::class);
                foreach ($variantRow['steps'] as $stepPosition => $text) {
                    RecipeStep::query()->create([
                        'recipe_variant_id' => $variant->getKey(), 'text' => $text, 'position' => $stepPosition + 1,
                    ]);
                }
            }

            return $target;
        });
    }

    /**
     * Set a recipe archive state without deleting history.
     */
    public function setArchived(User $owner, Recipe $recipe, bool $archived): void
    {
        if ($recipe->getUserId() !== $owner->getKey()) {
            throw new InvalidArgumentException('Recipe does not belong to this company.');
        }
        $recipe->setAttribute('archived_at', $archived ? Carbon::now() : null);
        $recipe->save();
    }

    /**
     * Move a category one position within a company catalog.
     */
    public function moveCategory(User $owner, RecipeCategory $category, string $direction): void
    {
        if ($category->getUserId() !== $owner->getKey()) {
            throw new InvalidArgumentException('Recipe category does not belong to this company.');
        }

        DB::transaction(static function () use ($owner, $category, $direction): void {
            $target = Typer::assertInstance(RecipeCategory::query()->whereKey($category->getKey())->lockForUpdate()->firstOrFail(), RecipeCategory::class);
            $neighborQuery = RecipeCategory::query()->where('user_id', $owner->getKey());
            if ($direction === 'up') {
                $neighborQuery->where('position', '<', $target->getPosition())->orderByDesc('position');
            } else {
                $neighborQuery->where('position', '>', $target->getPosition())->orderBy('position');
            }
            $neighbor = $neighborQuery->lockForUpdate()->first();
            if (!$neighbor instanceof RecipeCategory) {
                return;
            }
            $targetPosition = $target->getPosition();
            $target->setAttribute('position', $neighbor->getPosition());
            $neighbor->setAttribute('position', $targetPosition);
            $target->save();
            $neighbor->save();
        });
    }

    /**
     * Move a recipe one position within its category.
     */
    public function moveRecipe(User $owner, Recipe $recipe, string $direction): void
    {
        if ($recipe->getUserId() !== $owner->getKey()) {
            throw new InvalidArgumentException('Recipe does not belong to this company.');
        }

        DB::transaction(static function () use ($recipe, $direction): void {
            $target = Typer::assertInstance(Recipe::query()->whereKey($recipe->getKey())->lockForUpdate()->firstOrFail(), Recipe::class);
            $neighborQuery = Recipe::query()->where('recipe_category_id', $target->getCategoryId());
            if ($direction === 'up') {
                $neighborQuery->where('position', '<', $target->getPosition())->orderByDesc('position');
            } else {
                $neighborQuery->where('position', '>', $target->getPosition())->orderBy('position');
            }
            $neighbor = $neighborQuery->lockForUpdate()->first();
            if (!$neighbor instanceof Recipe) {
                return;
            }
            $targetPosition = $target->getPosition();
            $target->setAttribute('position', $neighbor->getPosition());
            $neighbor->setAttribute('position', $targetPosition);
            $target->save();
            $neighbor->save();
        });
    }
}
