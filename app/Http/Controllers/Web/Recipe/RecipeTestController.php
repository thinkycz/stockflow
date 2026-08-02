<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Recipe;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\RecipeValidity;
use App\Models\Recipe;
use App\Models\RecipeTestAttempt;
use App\Models\User;
use App\Models\Worker;
use App\Services\RecipeTestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class RecipeTestController
{
    use ValidatesWebRequests;

    /**
     * Start a new test attempt for a selected worker.
     */
    public function store(Request $request): RedirectResponse
    {
        $actor = User::mustAuth();
        if ($actor->isAdmin()) {
            \abort(403);
        }
        $owner = $actor->resolveScopeUser();
        $validity = RecipeValidity::inject($owner->getKey());
        $validated = $this->validateRequest($request, [
            'recipe_id' => $validity->recipeId()->required()->toArray(),
            'worker_id' => $validity->workerId()->required()->toArray(),
        ]);
        $recipe = Typer::assertInstance(Recipe::query()->where('user_id', $owner->getKey())->whereNull('archived_at')->whereKey($validated->assertInt('recipe_id'))->firstOrFail(), Recipe::class);
        $worker = Typer::assertInstance(Worker::query()->where('user_id', $owner->getKey())->whereKey($validated->assertInt('worker_id'))->firstOrFail(), Worker::class);
        $attempt = (new RecipeTestService())->start($actor, $worker, $recipe);

        return Resolver::resolveRedirector()->route('recipe-tests.show', $attempt->getKey());
    }

    /**
     * Display an owned in-progress attempt.
     */
    public function show(RecipeTestAttempt $recipeTest): RedirectResponse|Response
    {
        $actor = User::mustAuth();
        $attempt = $this->ownedAttempt($actor, $recipeTest);
        if ($attempt->getSubmittedAt() !== null) {
            return Resolver::resolveRedirector()->route('recipes.show', $attempt->getRecipeId());
        }

        return Inertia::render('recipes/Test', $this->payload($attempt, false));
    }

    /**
     * Submit and display the result of an owned attempt.
     */
    public function update(Request $request, RecipeTestAttempt $recipeTest): Response
    {
        $actor = User::mustAuth();
        $attempt = $this->ownedAttempt($actor, $recipeTest);
        $validity = RecipeValidity::inject($actor->resolveScopeUser()->getKey());
        $validated = $this->validateRequest($request, [
            'tokens' => $validity->tokens()->required()->toArray(),
            'tokens.*' => $validity->token()->required()->toArray(),
        ]);
        $tokens = \array_values(Typer::assertStringArray($validated->assertArray('tokens')));
        $submitted = (new RecipeTestService())->submit($actor, $attempt, $tokens);

        return Inertia::render('recipes/Test', $this->payload($submitted, true));
    }

    /**
     * Resolve an attempt owned by the authenticated audit account.
     */
    private function ownedAttempt(User $actor, RecipeTestAttempt $attempt): RecipeTestAttempt
    {
        return Typer::assertInstance(RecipeTestAttempt::query()
            ->where('user_id', $actor->resolveScopeUser()->getKey())
            ->where('actor_user_id', $actor->getKey())
            ->whereKey($attempt->getKey())->firstOrFail(), RecipeTestAttempt::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(RecipeTestAttempt $attempt, bool $withResult): array
    {
        $correct = $attempt->getCorrectStepsSnapshot();
        $snapshot = $attempt->getVariantSnapshot();
        $snapshotSteps = [];
        $ingredients = [];
        if ($snapshot !== null) {
            foreach (Typer::assertArray($snapshot['steps'] ?? []) as $value) {
                $row = Typer::assertStringKeyArray(Typer::assertArray($value));
                $token = Typer::assertString($row['token'] ?? null);
                $snapshotSteps[$token] = [
                    'action_key' => Typer::assertString($row['action_key'] ?? 'other'),
                    'source_text' => Typer::assertNullableString($row['source_text'] ?? null),
                ];
            }
            foreach (Typer::assertArray($snapshot['ingredients'] ?? []) as $value) {
                $row = Typer::assertStringKeyArray(Typer::assertArray($value));
                $ingredients[] = $row;
            }
        }
        $byToken = [];
        foreach ($correct as $step) {
            $byToken[$step['token']] = $step['text'];
        }
        $presented = [];
        foreach ($attempt->getPresentedTokens() as $token) {
            $presented[] = [
                'token' => $token,
                'text' => $byToken[$token],
                'action_key' => $snapshotSteps[$token]['action_key'] ?? 'other',
                'source_text' => $snapshotSteps[$token]['source_text'] ?? null,
            ];
        }

        return [
            'attempt' => [
                'id' => $attempt->getKey(), 'recipe_id' => $attempt->getRecipeId(),
                'recipe_name' => $attempt->getRecipeName(), 'variant_name' => $attempt->getVariantName(),
                'worker_name' => $attempt->getWorkerName(), 'ingredients' => $ingredients, 'steps' => $presented,
            ],
            'result' => $withResult ? [
                'score' => $attempt->getScore(), 'passed' => $attempt->isPassed(),
                'correct_steps' => \array_column($correct, 'text'), 'ingredients' => $ingredients,
            ] : null,
        ];
    }
}
