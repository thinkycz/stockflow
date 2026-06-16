<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Statement;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Statement>
 */
class StatementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $now = Carbon::now();

        return [
            'user_id' => static fn(): int => UserFactory::new()->createOne()->getKey(),
            'store_id' => static fn(): int => Store::factory()->createOne()->getKey(),
            'year' => $now->year,
            'month' => $now->month,
        ];
    }

    /**
     * Indicate the statement belongs to the given user.
     */
    public function byUser(User $user): self
    {
        return $this->state(fn(): array => [
            'user_id' => $user->getKey(),
        ]);
    }

    /**
     * Indicate the statement is for the given store.
     */
    public function forStore(Store $store): self
    {
        return $this->state(fn(): array => [
            'store_id' => $store->getKey(),
            'user_id' => $store->getUserId(),
        ]);
    }

    /**
     * Indicate the statement is for a specific year and month.
     */
    public function forMonth(int $year, int $month): self
    {
        return $this->state(fn(): array => [
            'year' => $year,
            'month' => $month,
        ]);
    }
}
