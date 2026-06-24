<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InventoryCount;
use App\Models\Item;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<InventoryCount>
 */
class InventoryCountFactory extends Factory
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
            'store_id' => static fn(): int => Store::factory()->createOne()->getKey(),
            'item_id' => static fn(): int => Item::factory()->createOne()->getKey(),
            'quantity' => $this->faker->numberBetween(0, 100),
            'counted_at' => Carbon::now(),
            'created_by' => null,
            'note' => null,
        ];
    }

    /**
     * Indicate the count belongs to the given user.
     */
    public function byUser(User $user): self
    {
        return $this->state(fn(): array => [
            'user_id' => $user->getKey(),
        ]);
    }

    /**
     * Indicate the count is for the given store.
     */
    public function forStore(Store $store): self
    {
        return $this->state(fn(): array => [
            'store_id' => $store->getKey(),
            'user_id' => $store->getUserId(),
        ]);
    }

    /**
     * Indicate the count is for the given item.
     */
    public function forItem(Item $item): self
    {
        return $this->state(fn(): array => [
            'item_id' => $item->getKey(),
            'user_id' => $item->getUserId(),
        ]);
    }

    /**
     * Indicate the count was taken within the last N days.
     */
    public function recent(int $days = 30): self
    {
        return $this->state(fn(): array => [
            'counted_at' => Carbon::now()->subMinutes($this->faker->numberBetween(1, $days * 24 * 60)),
        ]);
    }
}
