<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OperationalActivityTypeEnum;
use App\Models\Recipe;
use App\Models\RecipeInstruction;
use App\Models\RecipeTestAttempt;
use App\Models\RecipeVariant;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Resolver;
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

        $variants = $recipe->variants()->has('instructions', '>=', 2)->with('instructions')->get();
        if ($variants->isEmpty()) {
            throw new InvalidArgumentException('Recipe has no testable variant.');
        }
        $variant = Typer::assertInstance($variants->get(\random_int(0, $variants->count() - 1)), RecipeVariant::class);
        $instructions = $variant->getInstructions();
        if ($instructions->count() < 2) {
            throw new InvalidArgumentException('Recipe variant must have at least two steps.');
        }

        $correct = [];
        $snapshotInstructions = [];
        foreach ($instructions as $value) {
            $instruction = Typer::assertInstance($value, RecipeInstruction::class);
            $token = Str::uuid()->toString();
            $correct[] = ['token' => $token, 'text' => $instruction->getText()];
            $snapshotInstructions[] = [
                'token' => $token,
                'instruction_id' => $instruction->getKey(),
                'type' => $instruction->getType(),
                'text' => $instruction->getText(),
                'action_key' => $instruction->getActionKey(),
                'quantity_value' => $instruction->getQuantityValue(),
                'quantity_text' => $instruction->getQuantityText(),
                'unit' => $instruction->getUnit(),
                'ingredient_name' => $instruction->getIngredientName(),
                'target' => $instruction->getTarget(),
                'icon_group' => $instruction->getIconGroup(),
                'is_inferred' => $instruction->isInferred(),
            ];
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
            'variant_snapshot' => [
                'variant_name' => $variant->getName(),
                'instructions' => $snapshotInstructions,
            ],
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
            $submittedAt = Carbon::now();
            $locked->setAttribute('submitted_at', $submittedAt);
            $locked->save();

            if ($locked->getSessionId() === null) {
                OperationalActivityService::dispatchToCompany(
                    $locked->isPassed() ? OperationalActivityTypeEnum::RECIPE_TEST_PASSED : OperationalActivityTypeEnum::RECIPE_TEST_FAILED,
                    $actor,
                    $submittedAt->toIso8601String(),
                    Resolver::resolveUrlGenerator()->route('recipe-test-results.show', ['recipeTest' => $locked->getKey()]),
                    [
                        'Slack worker' => $locked->getWorkerName(),
                        'Slack recipe' => $locked->getRecipeName(),
                        'Slack recipe test score' => (string) $locked->getScore() . ' %',
                        'Slack recipe test result' => $locked->isPassed() ? 'Úspěšný' : 'Neúspěšný',
                    ],
                );
            }

            return $locked;
        });
    }
}
