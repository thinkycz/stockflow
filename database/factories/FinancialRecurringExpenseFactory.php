<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FinancialRecurringExpense;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<FinancialRecurringExpense>
 */
class FinancialRecurringExpenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $store = Store::factory()->createOne();

        return ['user_id' => $store->getUserId(), 'store_id' => $store->getKey(), 'starts_on' => Carbon::now()->startOfMonth()];
    }

    /**
     * Associate with a store.
     */
    public function forStore(Store $store): self
    {
        return $this->state(fn(): array => ['user_id' => $store->getUserId(), 'store_id' => $store->getKey()]);
    }
}
