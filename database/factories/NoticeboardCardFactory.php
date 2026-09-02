<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NoticeboardCardColorEnum;
use App\Enums\NoticeboardCardLabelEnum;
use App\Enums\NoticeboardCardSizeEnum;
use App\Models\NoticeboardCard;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NoticeboardCard>
 */
class NoticeboardCardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = UserFactory::new()->admin()->createOne();
        $store = Store::factory()->createOne(['user_id' => $user->getKey()]);

        return [
            'user_id' => $user->getKey(),
            'store_id' => $store->getKey(),
            'created_by_user_id' => $user->getKey(),
            'updated_by_user_id' => $user->getKey(),
            'title' => $this->faker->sentence(4),
            'body_html' => '<p>' . $this->faker->sentence() . '</p>',
            'body_text' => $this->faker->sentence(),
            'label' => NoticeboardCardLabelEnum::Information->value,
            'color' => NoticeboardCardColorEnum::Yellow->value,
            'size' => NoticeboardCardSizeEnum::Medium->value,
            'expires_at' => null,
            'lock_version' => 1,
        ];
    }
}
