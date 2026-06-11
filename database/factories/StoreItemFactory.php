<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Item;
use App\Models\Store;
use App\Models\StoreItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreItem>
 */
class StoreItemFactory extends Factory
{
    protected $model = StoreItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'item_id' => Item::factory(),
            'quantity' => $this->faker->randomFloat(3, 0, 100),
        ];
    }
}
