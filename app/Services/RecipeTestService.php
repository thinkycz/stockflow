<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Recipe;
use App\Models\RecipeStep;
use App\Models\RecipeTestAttempt;
use App\Models\RecipeVariant;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Typer;

class RecipeTestService
{
    /**
     * Start one attempt with a uniformly selected recipe variant.
     */
    public function start(User $actor, Worker $worker, Recipe $recipe): RecipeTestAttempt
    {
        $owner = $actor->resolveScopeUser();
        if ($actor->isAdmin() || $worker->getUserId() !== $owner->getKey() || $recipe->getUserId() !== $owner->getKey() || $recipe->isArchived()) {
            throw new InvalidArgumentException('Recipe test is not available.');
        }

        $variants = $recipe->variants()->with('steps')->get();
        if ($variants->isEmpty()) {
            throw new InvalidArgumentException('Recipe has no testable variant.');
        }
        $variant = Typer::assertInstance($variants->get(\random_int(0, $variants->count() - 1)), RecipeVariant::class);
        $steps = $variant->getSteps();
        if ($steps->count() < 2) {
            throw new InvalidArgumentException('Recipe variant must have at least two steps.');
        }

        $correct = [];
        foreach ($steps as $value) {
            $step = Typer::assertInstance($value, RecipeStep::class);
            $correct[] = ['token' => Str::uuid()->toString(), 'text' => $step->getText()];
        }
        $presented = \array_column($correct, 'token');
        \shuffle($presented);
        if ($presented === \array_column($correct, 'token')) {
            $first = \array_shift($presented);
            if (\is_string($first)) {
                $presented[] = $first;
            }
        }

        return Typer::assertInstance(RecipeTestAttempt::query()->create([
            'user_id' => $owner->getKey(),
            'recipe_id' => $recipe->getKey(),
            'recipe_variant_id' => $variant->getKey(),
            'worker_id' => $worker->getKey(),
            'actor_user_id' => $actor->getKey(),
            'recipe_name' => $recipe->getName(),
            'variant_name' => $variant->getName(),
            'worker_name' => $worker->getFullName(),
            'actor_name' => $actor->getEmail(),
            'correct_steps' => $correct,
            'presented_tokens' => $presented,
            'submitted_tokens' => null,
            'score' => null,
            'passed' => null,
            'started_at' => Carbon::now(),
            'submitted_at' => null,
        ]), RecipeTestAttempt::class);
    }

    /**
     * @param list<string> $submittedTokens
     */
    public function submit(User $actor, RecipeTestAttempt $attempt, array $submittedTokens): RecipeTestAttempt
    {
        return DB::transaction(static function () use ($actor, $attempt, $submittedTokens): RecipeTestAttempt {
            $locked = Typer::assertInstance(RecipeTestAttempt::query()->whereKey($attempt->getKey())->lockForUpdate()->firstOrFail(), RecipeTestAttempt::class);
            if ($locked->getActorUserId() !== $actor->getKey() || $locked->getUserId() !== $actor->resolveScopeUser()->getKey()) {
                throw new InvalidArgumentException('Recipe test attempt does not belong to this account.');
            }
            if ($locked->getSubmittedAt() !== null) {
                throw new RuntimeException('Recipe test attempt was already submitted.');
            }

            $correctTokens = \array_column($locked->getCorrectStepsSnapshot(), 'token');
            $expected = $correctTokens;
            $received = $submittedTokens;
            \sort($expected);
            \sort($received);
            if ($expected !== $received || \count($submittedTokens) !== \count(\array_unique($submittedTokens))) {
                throw new InvalidArgumentException('Submitted recipe steps are invalid.');
            }

            $correctPositions = 0;
            foreach ($correctTokens as $position => $token) {
                if (($submittedTokens[$position] ?? null) === $token) {
                    ++$correctPositions;
                }
            }
            $score = (int) \round(($correctPositions / \count($correctTokens)) * 100);
            $locked->setAttribute('submitted_tokens', $submittedTokens);
            $locked->setAttribute('score', $score);
            $locked->setAttribute('passed', $score === 100);
            $locked->setAttribute('submitted_at', Carbon::now());
            $locked->save();

            return $locked;
        });
    }
}
