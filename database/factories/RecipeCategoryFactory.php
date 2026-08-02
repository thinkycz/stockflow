<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RecipeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeCategory>
 */
class RecipeCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['user_id' => UserFactory::new()->admin(), 'name' => $this->faker->unique()->words(2, true), 'position' => 1];
    }
}
