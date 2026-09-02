<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ShiftShareLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShiftShareLink>
 */
class ShiftShareLinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => static fn(): int => UserFactory::new()->admin()->createOne()->getKey(),
            'store_id' => static fn(array $attributes): int => StoreFactory::new()->createOne([
                'user_id' => $attributes['user_id'],
            ])->getKey(),
            'name' => $this->faker->unique()->words(2, true),
            'token' => Str::random(64),
        ];
    }
}
