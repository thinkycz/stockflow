<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FinancialRecurringExpense;
use App\Models\FinancialRecurringExpenseVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<FinancialRecurringExpenseVersion>
 */
class FinancialRecurringExpenseVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'financial_recurring_expense_id' => static fn(): int => FinancialRecurringExpense::factory()->createOne()->getKey(),
            'effective_from' => Carbon::now()->startOfMonth(),
            'label' => $this->faker->words(2, true),
            'amount' => $this->faker->randomFloat(2, 100, 10000),
            'due_day' => $this->faker->numberBetween(1, 31),
            'note' => null,
        ];
    }
}
