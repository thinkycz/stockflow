<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ChecklistShiftEnum;
use App\Models\ChecklistDay;
use App\Models\ChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistItem>
 */
class ChecklistItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checklist_day_id' => static fn(): int => ChecklistDay::factory()->createOne()->getKey(),
            'shift' => ChecklistShiftEnum::Morning->value, 'text' => $this->faker->sentence(),
            'position' => 1, 'lock_version' => 1,
        ];
    }
}
