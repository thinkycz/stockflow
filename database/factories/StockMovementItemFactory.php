<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AdjustmentReasonEnum;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockMovementItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovementItem>
 */
class StockMovementItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stock_movement_id' => StockMovement::factory(),
            'item_id' => Item::factory(),
            'quantity' => $this->faker->randomFloat(3, 1, 50),
            'total' => $this->faker->randomFloat(2, 1, 5000),
            'quantity_before' => null,
            'quantity_after' => null,
            'quantity_difference' => null,
            'adjustment_reason' => null,
        ];
    }

    /**
     * Indicate that the row is for an adjustment movement.
     */
    public function adjustment(AdjustmentReasonEnum $reason = AdjustmentReasonEnum::OTHER): self
    {
        return $this->state(function () use ($reason): array {
            $before = $this->faker->randomFloat(3, 0, 100);
            $after = $this->faker->randomFloat(3, 0, 100);

            return [
                'quantity' => null,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'quantity_difference' => $after - $before,
                'adjustment_reason' => $reason->value,
            ];
        });
    }
}
