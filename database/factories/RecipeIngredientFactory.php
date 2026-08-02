<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RecipeIngredient;
use App\Models\RecipeVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeIngredient>
 */
class RecipeIngredientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipe_variant_id' => RecipeVariant::factory(),
            'position' => 1,
            'quantity_value' => 100,
            'quantity_text' => null,
            'unit' => 'g',
            'name' => 'milk',
            'icon_group' => 'water_milk',
            'source_text' => '100g milk',
        ];
    }
}
