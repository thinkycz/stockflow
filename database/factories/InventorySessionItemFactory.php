<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InventorySession;
use App\Models\InventorySessionItem;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventorySessionItem>
 */
class InventorySessionItemFactory extends Factory
{
    protected $model = InventorySessionItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => static fn(): int => InventorySession::factory()->createOne()->getKey(),
            'item_id' => static fn(): int => Item::factory()->createOne()->getKey(),
            'quantity' => $this->faker->numberBetween(0, 100),
            'note' => null,
        ];
    }
}
