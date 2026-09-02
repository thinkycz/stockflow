<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ShiftPreset;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftPreset>
 */
class ShiftPresetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = UserFactory::new()->admin()->createOne();

        return [
            'user_id' => $user->getKey(),
            'store_id' => Store::factory()->createOne([
                'user_id' => $user->getKey(),
                'is_warehouse' => false,
            ])->getKey(),
            'name' => $this->faker->unique()->words(2, true),
            'start_time' => '09:00',
            'end_time' => '17:00',
        ];
    }
}
