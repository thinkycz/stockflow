<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ChecklistDay;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistDay>
 */
class ChecklistDayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $store = Store::factory()->createOne(['is_warehouse' => false]);

        return ['user_id' => $store->getUserId(), 'store_id' => $store->getKey(), 'date' => $this->faker->unique()->date()];
    }
}
