<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RecipeStep;
use App\Models\RecipeVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeStep>
 */
class RecipeStepFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['recipe_variant_id' => RecipeVariant::factory(), 'text' => $this->faker->sentence(), 'position' => 1];
    }
}
