<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InventorySession;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<InventorySession>
 */
class InventorySessionFactory extends Factory
{
    protected $model = InventorySession::class;

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
            'created_by' => null,
            'counted_at' => Carbon::now(),
            'note' => null,
        ];
    }

    /**
     * Indicate the session belongs to the given user.
     */
    public function byUser(User $user): self
    {
        return $this->state(fn(): array => [
            'user_id' => $user->getKey(),
        ]);
    }

    /**
     * Indicate the session is for the given store.
     */
    public function forStore(Store $store): self
    {
        return $this->state(fn(): array => [
            'store_id' => $store->getKey(),
            'user_id' => $store->getUserId(),
        ]);
    }

    /**
     * Indicate the session was created by the given user.
     */
    public function byCreator(User $user): self
    {
        return $this->state(fn(): array => [
            'created_by' => $user->getKey(),
        ]);
    }

    /**
     * Indicate the session was taken within the last N days.
     */
    public function recent(int $days = 30): self
    {
        return $this->state(fn(): array => [
            'counted_at' => Carbon::now()->subMinutes($this->faker->numberBetween(1, $days * 24 * 60)),
        ]);
    }
}
