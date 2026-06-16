<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Statement;
use App\Models\StatementDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatementDay>
 */
class StatementDayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'statement_id' => static fn(): int => Statement::factory()->createOne()->getKey(),
            'date' => $this->faker->unique()->date('Y-m-d'),
            'cash' => 0,
            'card' => 0,
            'wolt' => 0,
            'bolt' => 0,
            'foodora' => 0,
            'total' => 0,
        ];
    }
}
