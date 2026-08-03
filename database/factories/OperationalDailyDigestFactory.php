<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OperationalDailyDigestStatusEnum;
use App\Models\OperationalDailyDigest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationalDailyDigest>
 */
class OperationalDailyDigestFactory extends Factory
{
    /**
     * Define a daily digest.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = CarbonImmutable::now('Europe/Prague')->subDay()->startOfDay();

        return [
            'company_user_id' => static fn(): int => UserFactory::new()->admin()->createOne()->getKey(),
            'digest_date' => $date->toDateString(),
            'period_start' => $date->utc(),
            'period_end' => $date->addDay()->utc(),
            'status' => OperationalDailyDigestStatusEnum::PENDING->value,
            'snapshot' => [
                'date' => $date->toDateString(),
                'title' => 'Denní provozní souhrn',
                'intro' => 'Bez provozních milníků.',
                'period_start' => $date->utc()->toIso8601String(),
                'period_end' => $date->addDay()->utc()->toIso8601String(),
                'activity_count' => 0,
                'sections' => [],
            ],
            'activity_count' => 0,
            'attempt_count' => 0,
            'last_error' => null,
            'queued_at' => null,
            'sent_at' => null,
        ];
    }
}
