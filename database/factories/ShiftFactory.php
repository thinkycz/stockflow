<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Shift;
use App\Models\Store;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hour = $this->faker->numberBetween(8, 18);
        $user = UserFactory::new()->createOne();
        $worker = Worker::factory()->createOne(['user_id' => $user->getKey()]);

        return [
            'user_id' => $user->getKey(),
            'store_id' => Store::factory()->createOne([
                'user_id' => $user->getKey(),
                'is_warehouse' => false,
            ])->getKey(),
            'worker_id' => $worker->getKey(),
            'date' => $this->faker->date('Y-m-d'),
            'start_time' => \sprintf('%02d:00', $hour),
            'end_time' => \sprintf('%02d:00', $hour + 4),
            'hourly_rate' => $worker->getHourlyRate(),
        ];
    }
}
