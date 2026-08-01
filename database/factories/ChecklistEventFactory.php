<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ChecklistEventActionEnum;
use App\Models\ChecklistEvent;
use App\Models\ChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistEvent>
 */
class ChecklistEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $item = ChecklistItem::factory()->createOne();

        return [
            'checklist_day_id' => $item->getChecklistDayId(), 'checklist_item_id' => $item->getKey(),
            'action' => ChecklistEventActionEnum::Completed->value, 'created_at' => $this->faker->dateTime(),
        ];
    }
}
