<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FinancialSourceTypeEnum;
use App\Models\FinancialReport;
use App\Models\FinancialReportOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialReportOverride>
 */
class FinancialReportOverrideFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'financial_report_id' => static fn(): int => FinancialReport::factory()->createOne()->getKey(),
            'source_type' => FinancialSourceTypeEnum::REVENUE->value,
            'source_key' => 'cash',
            'amount' => 1000,
        ];
    }
}
