<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AssistantActionClassificationEnum;
use App\Enums\AssistantActionStatusEnum;
use App\Models\AssistantActionAudit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<AssistantActionAudit>
 */
class AssistantActionAuditFactory extends Factory
{
    /**
     * Define an assistant action audit snapshot.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_user_id' => static fn(): int => UserFactory::new()->admin()->createOne()->getKey(),
            'actor_email' => $this->faker->safeEmail(),
            'conversation_id' => $this->faker->uuid(),
            'invocation_id' => $this->faker->uuid(),
            'tool_call_id' => $this->faker->unique()->bothify('call_????????'),
            'tool_invocation_id' => $this->faker->uuid(),
            'tool_name' => 'query_inventory',
            'domain' => 'inventory',
            'operation' => 'inventory.summary',
            'classification' => AssistantActionClassificationEnum::READ->value,
            'status' => AssistantActionStatusEnum::SUCCEEDED->value,
            'store_id' => null,
            'store_name' => null,
            'target_type' => null,
            'target_id' => null,
            'arguments' => [],
            'result_summary' => [],
            'error_summary' => null,
            'proposed_at' => Carbon::now('UTC'),
            'decided_at' => null,
            'started_at' => Carbon::now('UTC'),
            'completed_at' => Carbon::now('UTC'),
            'duration_ms' => 1.0,
        ];
    }
}
