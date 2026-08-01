<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FinancialReportStatusEnum;
use App\Models\FinancialReport;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<FinancialReport>
 */
class FinancialReportFactory extends Factory
{
    /**
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
            'status' => FinancialReportStatusEnum::OPEN->value,
        ];
    }

    /**
     * Associate a report with a store.
     */
    public function forStore(Store $store): self
    {
        return $this->state(fn(): array => ['user_id' => $store->getUserId(), 'store_id' => $store->getKey()]);
    }

    /**
     * Set report month.
     */
    public function forMonth(int $year, int $month): self
    {
        return $this->state(fn(): array => ['year' => $year, 'month' => $month]);
    }

    /**
     * Associate a report with an admin.
     */
    public function byUser(User $user): self
    {
        return $this->state(fn(): array => ['user_id' => $user->getKey()]);
    }
}
