<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\RecipeTestAttempt;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Support\Typer;

/**
 * @extends Factory<RecipeTestAttempt>
 */
class RecipeTestAttemptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $recipe = Recipe::factory()->createOne();
        $owner = Typer::assertInstance(User::query()->findOrFail($recipe->getUserId()), User::class);
        $store = Store::factory()->createOne(['user_id' => $owner->getKey()]);
        $actor = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);
        $worker = Worker::factory()->createOne(['user_id' => $owner->getKey()]);
        $correct = [['token' => 'first', 'text' => 'First'], ['token' => 'second', 'text' => 'Second']];

        return [
            'user_id' => $owner->getKey(), 'recipe_id' => $recipe->getKey(), 'recipe_variant_id' => null,
            'worker_id' => $worker->getKey(), 'actor_user_id' => $actor->getKey(),
            'recipe_name' => $recipe->getName(), 'variant_name' => null,
            'worker_name' => $worker->getFullName(), 'actor_name' => $actor->getEmail(),
            'correct_steps' => $correct, 'presented_tokens' => ['second', 'first'], 'submitted_tokens' => null,
            'score' => null, 'passed' => null, 'started_at' => Carbon::now(), 'submitted_at' => null,
        ];
    }
}
