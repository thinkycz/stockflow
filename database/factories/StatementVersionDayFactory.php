<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StatementVersion;
use App\Models\StatementVersionDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatementVersionDay>
 */
class StatementVersionDayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'version_id' => static fn(): int => StatementVersion::factory()->createOne()->getKey(),
            'date' => $this->faker->unique()->date('Y-m-d'),
            'cash' => 0,
            'card' => 0,
            'wolt' => 0,
            'bolt' => 0,
            'bolt_cash' => 0,
            'foodora' => 0,
            'total' => 0,
        ];
    }

    /**
     * Indicate the day row is for the given version.
     */
    public function forVersion(StatementVersion $version): self
    {
        return $this->state(fn(): array => [
            'version_id' => $version->getKey(),
        ]);
    }
}
