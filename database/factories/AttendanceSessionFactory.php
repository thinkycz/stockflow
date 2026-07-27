<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AttendanceSession;
use App\Models\Store;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceSession>
 */
class AttendanceSessionFactory extends Factory
{
    public function definition(): array
    {
        $user = UserFactory::new()->admin()->createOne();
        $worker = Worker::factory()->createOne(['user_id' => $user->getKey()]);

        return [
            'user_id' => $user->getKey(),
            'store_id' => Store::factory()->createOne(['user_id' => $user->getKey()])->getKey(),
            'worker_id' => $worker->getKey(),
            'created_by_user_id' => $user->getKey(),
            'active_worker_id' => null,
            'hourly_rate' => $worker->getHourlyRate(),
            'started_at' => $this->faker->dateTimeBetween('-1 month', '-1 day'),
            'ended_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
        ];
    }
}
