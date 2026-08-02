<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\RecipeVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeVariant>
 */
class RecipeVariantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['recipe_id' => Recipe::factory(), 'name' => 'M', 'position' => 1];
    }
}
