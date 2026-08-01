<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FinancialDirectionEnum;
use App\Models\FinancialReport;
use App\Models\FinancialReportManualRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialReportManualRow>
 */
class FinancialReportManualRowFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'financial_report_id' => static fn(): int => FinancialReport::factory()->createOne()->getKey(),
            'direction' => FinancialDirectionEnum::EXPENSE->value,
            'label' => $this->faker->words(2, true),
            'occurred_on' => $this->faker->date(),
            'amount' => $this->faker->randomFloat(2, 0, 10000),
            'note' => null,
        ];
    }
}
