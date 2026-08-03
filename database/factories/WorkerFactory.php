<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Worker>
 */
class WorkerFactory extends Factory
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
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'hourly_rate' => $this->faker->randomFloat(2, 150, 500),
            'attendance_rating_enabled' => true,
        ];
    }
}
