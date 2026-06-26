<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Statement;
use App\Models\StatementVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<StatementVersion>
 */
class StatementVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => static fn(): int => UserFactory::new()->createOne()->getKey(),
            'statement_id' => static fn(): int => Statement::factory()->createOne()->getKey(),
            'created_by' => null,
            'snapshot_at' => Carbon::now(),
            'note' => null,
        ];
    }

    /**
     * Indicate the version belongs to the given user.
     */
    public function byUser(User $user): self
    {
        return $this->state(fn(): array => [
            'user_id' => $user->getKey(),
        ]);
    }

    /**
     * Indicate the version is for the given statement.
     */
    public function forStatement(Statement $statement): self
    {
        return $this->state(fn(): array => [
            'statement_id' => $statement->getKey(),
            'user_id' => $statement->getUserId(),
        ]);
    }

    /**
     * Indicate the version was created by the given user.
     */
    public function byCreator(User $user): self
    {
        return $this->state(fn(): array => [
            'created_by' => $user->getKey(),
        ]);
    }
}
