<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ChecklistShiftEnum;
use App\Enums\ChecklistTemplateScopeEnum;
use App\Models\ChecklistTemplateTask;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistTemplateTask>
 */
class ChecklistTemplateTaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $store = Store::factory()->createOne(['is_warehouse' => false]);

        return [
            'user_id' => $store->getUserId(), 'store_id' => $store->getKey(),
            'scope' => ChecklistTemplateScopeEnum::Daily->value, 'weekday' => null,
            'shift' => ChecklistShiftEnum::Morning->value, 'text' => $this->faker->sentence(), 'position' => 1,
        ];
    }
}
