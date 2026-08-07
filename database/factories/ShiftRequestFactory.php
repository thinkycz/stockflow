<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ShiftRequest;
use App\Models\Store;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftRequest>
 */
class ShiftRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = UserFactory::new()->createOne();
        $worker = Worker::factory()->createOne(['user_id' => $user->getKey()]);

        return [
            'user_id' => $user->getKey(),
            'store_id' => Store::factory()->createOne(['user_id' => $user->getKey(), 'is_warehouse' => false])->getKey(),
            'worker_id' => $worker->getKey(),
            'date' => $this->faker->date('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '17:00',
        ];
    }
}
