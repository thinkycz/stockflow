<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StoreStatusEnum;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
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
            'name' => $this->faker->unique()->company(),
            'address' => $this->faker->streetAddress() . ', ' . $this->faker->city(),
            'status' => StoreStatusEnum::ACTIVE->value,
            'is_warehouse' => false,
            'notes' => null,
        ];
    }

    /**
     * Indicate that the store is the user's warehouse.
     */
    public function warehouse(): self
    {
        return $this->state(fn(): array => [
            'name' => 'Warehouse',
            'is_warehouse' => true,
        ]);
    }

    /**
     * Indicate that the store is inactive.
     */
    public function inactive(): self
    {
        return $this->state(fn(): array => [
            'status' => StoreStatusEnum::INACTIVE->value,
        ]);
    }
}
