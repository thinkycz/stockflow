<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StockMovementTypeEnum;
use App\Models\StockMovementSequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovementSequence>
 */
class StockMovementSequenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => StockMovementTypeEnum::INCOMING->value,
            'year' => (int) \date('Y'),
            'last_number' => 0,
        ];
    }

    /**
     * Indicate the sequence is for incoming movements.
     */
    public function incoming(): self
    {
        return $this->state(fn(): array => [
            'type' => StockMovementTypeEnum::INCOMING->value,
        ]);
    }

    /**
     * Indicate the sequence is for outgoing movements.
     */
    public function outgoing(): self
    {
        return $this->state(fn(): array => [
            'type' => StockMovementTypeEnum::TRANSFER->value,
        ]);
    }

    /**
     * Indicate the sequence is for adjustment movements.
     */
    public function adjustment(): self
    {
        return $this->state(fn(): array => [
            'type' => StockMovementTypeEnum::ADJUSTMENT->value,
        ]);
    }
}
