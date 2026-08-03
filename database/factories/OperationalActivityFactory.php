<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OperationalActivityTypeEnum;
use App\Models\OperationalActivity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<OperationalActivity>
 */
class OperationalActivityFactory extends Factory
{
    /**
     * Define an operational activity snapshot.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_user_id' => static fn(): int => UserFactory::new()->admin()->createOne()->getKey(),
            'type' => OperationalActivityTypeEnum::STATEMENT_SAVED->value,
            'actor_email' => $this->faker->safeEmail(),
            'occurred_at' => Carbon::now('UTC'),
            'url' => '/reports',
            'store_contexts' => [],
            'facts' => [],
        ];
    }
}
