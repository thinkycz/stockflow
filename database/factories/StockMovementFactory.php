<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StockMovementOriginEnum;
use App\Enums\StockMovementTypeEnum;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => static fn(): int => UserFactory::new()->createOne()->getKey(),
            'number' => 'IN-' . Carbon::now()->year . '-' . \mb_str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', \STR_PAD_LEFT),
            'type' => StockMovementTypeEnum::INCOMING->value,
            'origin' => StockMovementOriginEnum::MANUAL->value,
            'occurred_at' => Carbon::now(),
            'store_id' => null,
            'source_store_id' => null,
            'note' => null,
            'created_by' => null,
            'total_quantity' => 0,
            'total_value' => 0,
        ];
    }

    /**
     * Indicate that the movement is incoming.
     */
    public function incoming(): self
    {
        return $this->state(fn(): array => [
            'type' => StockMovementTypeEnum::INCOMING->value,
        ]);
    }

    /**
     * Indicate that the movement is a transfer to the given store.
     */
    public function outgoing(Store $store): self
    {
        return $this->state(fn(): array => [
            'type' => StockMovementTypeEnum::TRANSFER->value,
            'store_id' => $store->getKey(),
        ]);
    }

    /**
     * Indicate that the movement is a transfer to the given store.
     */
    public function transfer(Store $store): self
    {
        return $this->outgoing($store);
    }

    /**
     * Indicate that the movement is manual consumption at the given store.
     */
    public function consumption(Store $store): self
    {
        return $this->state(fn(): array => [
            'type' => StockMovementTypeEnum::CONSUMPTION->value,
            'store_id' => $store->getKey(),
        ]);
    }

    /**
     * Indicate that the movement is an adjustment.
     */
    public function adjustment(): self
    {
        return $this->state(fn(): array => [
            'type' => StockMovementTypeEnum::ADJUSTMENT->value,
        ]);
    }

    /**
     * Indicate that the movement is created by the given user.
     */
    public function byUser(User|null $user): self
    {
        return $this->state(fn(): array => [
            'created_by' => $user?->getKey(),
        ]);
    }
}
