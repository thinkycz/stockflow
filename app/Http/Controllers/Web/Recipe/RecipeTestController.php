<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Recipe;

use App\Domain\Recipes\RecipeTestService;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\RecipeValidity;
use App\Models\RecipeTestAttempt;
use App\Models\User;
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
     * Display an owned in-progress attempt.
     */
    public function show(RecipeTestAttempt $recipeTest): RedirectResponse|Response
    {
        $actor = User::mustAuth();
        $attempt = $this->ownedAttempt($actor, $recipeTest);
        if ($attempt->getSubmittedAt() !== null) {
            return $attempt->getRecipeId() === null
                ? Resolver::resolveRedirector()->route('recipes.index')
                : Resolver::resolveRedirector()->route('recipes.show', $attempt->getRecipeId());
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
            ->whereNull('recipe_test_session_id')
            ->whereKey($attempt->getKey())->firstOrFail(), RecipeTestAttempt::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(RecipeTestAttempt $attempt, bool $withResult): array
    {
        $correct = $attempt->getCorrectStepsSnapshot();
        $snapshot = $attempt->getVariantSnapshot();
        $snapshotInstructions = [];
        if ($snapshot !== null) {
            foreach (Typer::assertArray($snapshot['instructions'] ?? []) as $value) {
                $row = Typer::assertStringKeyArray(Typer::assertArray($value));
                $token = Typer::assertString($row['token'] ?? null);
                $snapshotInstructions[$token] = [
                    'type' => Typer::assertString($row['type'] ?? 'action'),
                    'action_key' => Typer::assertString($row['action_key'] ?? 'other'),
                    'icon_group' => Typer::assertString($row['icon_group'] ?? 'neutral'),
                ];
            }
            foreach (Typer::assertArray($snapshot['steps'] ?? []) as $value) {
                $row = Typer::assertStringKeyArray(Typer::assertArray($value));
                $token = Typer::assertString($row['token'] ?? null);
                $snapshotInstructions[$token] = [
                    'type' => 'action',
                    'action_key' => Typer::assertString($row['action_key'] ?? 'other'),
                    'icon_group' => 'neutral',
                ];
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
                'type' => $snapshotInstructions[$token]['type'] ?? 'action',
                'action_key' => $snapshotInstructions[$token]['action_key'] ?? 'other',
                'icon_group' => $snapshotInstructions[$token]['icon_group'] ?? 'neutral',
            ];
        }

        return [
            'attempt' => [
                'id' => $attempt->getKey(), 'recipe_id' => $attempt->getRecipeId(),
                'recipe_name' => $attempt->getRecipeName(), 'variant_name' => $attempt->getVariantName(),
                'worker_name' => $attempt->getWorkerName(), 'instructions' => $presented,
            ],
            'result' => $withResult ? [
                'score' => $attempt->getScore(), 'passed' => $attempt->isPassed(),
                'correct_steps' => \array_column($correct, 'text'),
            ] : null,
        ];
    }
}
