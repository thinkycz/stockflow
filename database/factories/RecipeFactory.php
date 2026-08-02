<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\RecipeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = RecipeCategory::factory()->createOne();

        return ['user_id' => $category->getUserId(), 'recipe_category_id' => $category->getKey(), 'name' => $this->faker->words(3, true), 'note' => null, 'position' => 1, 'archived_at' => null];
    }
}
