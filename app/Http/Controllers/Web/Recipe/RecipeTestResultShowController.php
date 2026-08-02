<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Recipe;

use App\Models\RecipeTestAttempt;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Typer;

class RecipeTestResultShowController
{
    /**
     * Display one submitted attempt snapshot to an administrator.
     */
    public function __invoke(RecipeTestAttempt $recipeTest): Response
    {
        User::mustAuth();
        if ($recipeTest->getSubmittedAt() === null) {
            \abort(404);
        }
        $correct = $recipeTest->getCorrectStepsSnapshot();
        $byToken = [];
        foreach ($correct as $step) {
            $byToken[$step['token']] = $step['text'];
        }
        $submitted = [];
        foreach ($recipeTest->getSubmittedTokens() ?? [] as $token) {
            $submitted[] = $byToken[$token];
        }
        $snapshot = $recipeTest->getVariantSnapshot();
        $ingredients = [];
        $correctStepDetails = [];
        if ($snapshot !== null) {
            foreach (Typer::assertArray($snapshot['instructions'] ?? []) as $instruction) {
                $correctStepDetails[] = $instruction;
            }
            foreach (Typer::assertArray($snapshot['ingredients'] ?? []) as $ingredient) {
                $ingredients[] = $ingredient;
            }
            foreach (Typer::assertArray($snapshot['steps'] ?? []) as $step) {
                $correctStepDetails[] = $step;
            }
        }

        return Inertia::render('recipes/ResultShow', ['attempt' => [
            'id' => $recipeTest->getKey(), 'recipe_name' => $recipeTest->getRecipeName(), 'variant_name' => $recipeTest->getVariantName(),
            'worker_name' => $recipeTest->getWorkerName(), 'actor_name' => $recipeTest->getActorName(),
            'score' => $recipeTest->getScore(), 'passed' => $recipeTest->isPassed(),
            'started_at' => $recipeTest->getStartedAt()->toJSON(), 'submitted_at' => $recipeTest->getSubmittedAt()->toJSON(),
            'correct_steps' => \array_column($correct, 'text'), 'submitted_steps' => $submitted,
            'ingredients' => $ingredients, 'correct_step_details' => $correctStepDetails,
        ]]);
    }
}
