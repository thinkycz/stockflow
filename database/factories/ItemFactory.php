<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => static fn(): int => UserFactory::new()->createOne()->getKey(),
            'title' => $this->faker->unique()->words(2, true),
            'sku' => $this->faker->unique()->bothify('SKU-####'),
            'unit' => $this->faker->randomElement(['pcs', 'g', 'ml', 'bag']),
            'purchase_price' => $this->faker->randomFloat(2, 1, 100),
            'description' => null,
        ];
    }
}
