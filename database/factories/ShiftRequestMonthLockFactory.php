<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ShiftRequestMonthLock;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<ShiftRequestMonthLock>
 */
class ShiftRequestMonthLockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = UserFactory::new()->admin()->createOne();

        return [
            'user_id' => $user->getKey(),
            'store_id' => Store::factory()->createOne(['user_id' => $user->getKey(), 'is_warehouse' => false])->getKey(),
            'year' => Carbon::now()->addMonth()->year,
            'month' => Carbon::now()->addMonth()->month,
            'locked_at' => Carbon::now(),
            'locked_by_user_id' => $user->getKey(),
        ];
    }
}
