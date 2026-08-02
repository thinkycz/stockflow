<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Recipe;

use App\Models\Recipe;
use App\Models\RecipeTestAttempt;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Typer;

class RecipeTestResultIndexController
{
    public const int TAKE = 100;

    /**
     * Display worker results and recipe attempt history.
     */
    public function __invoke(Request $request): Response
    {
        $owner = User::mustAuth();
        $workers = Worker::query()->where('user_id', $owner->getKey())->orderBy('first_name')->orderBy('last_name')->get();
        $workerId = $request->integer('worker_id');
        $recipeId = $request->integer('recipe_id');
        $worker = $workerId > 0 ? $workers->first(static fn(Worker $value): bool => $workerId === $value->getKey()) : $workers->first();
        $rows = [];
        if ($worker instanceof Worker) {
            foreach (Recipe::query()->where('user_id', $owner->getKey())->orderBy('recipe_category_id')->orderBy('position')->get() as $value) {
                $recipe = Typer::assertInstance($value, Recipe::class);
                $attempts = $this->attemptsForRecipe($owner, $worker, $recipe)->whereNotNull('submitted_at');
                $latest = (clone $attempts)->orderByDesc('submitted_at')->first();
                $rows[] = [
                    'id' => $recipe->getKey(), 'name' => $recipe->getName(), 'archived' => $recipe->isArchived(),
                    'attempt_count' => $attempts->count(),
                    'latest' => $latest instanceof RecipeTestAttempt ? [
                        'id' => $latest->getKey(), 'variant_name' => $latest->getVariantName(), 'score' => $latest->getScore(),
                        'passed' => $latest->isPassed(), 'submitted_at' => $latest->getSubmittedAt()?->toJSON(),
                    ] : null,
                ];
            }
        }

        $history = ['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0];
        if ($worker instanceof Worker && $recipeId > 0) {
            $selectedRecipe = Recipe::query()->where('user_id', $owner->getKey())->whereKey($recipeId)->first();
            $attemptQuery = $selectedRecipe instanceof Recipe
                ? $this->attemptsForRecipe($owner, $worker, $selectedRecipe)
                : RecipeTestAttempt::query()->whereRaw('1 = 0');
            $paginator = $attemptQuery->whereNotNull('submitted_at')->orderByDesc('submitted_at')
                ->paginate(self::TAKE)->withQueryString();
            $paginator->through(static fn(RecipeTestAttempt $attempt): array => [
                'id' => $attempt->getKey(), 'variant_name' => $attempt->getVariantName(), 'score' => $attempt->getScore(),
                'passed' => $attempt->isPassed(), 'submitted_at' => $attempt->getSubmittedAt()?->toJSON(), 'actor_name' => $attempt->getActorName(),
            ]);
            $history = Typer::assertStringKeyArray($paginator->toArray());
        }

        return Inertia::render('recipes/Results', [
            'workers' => $workers->map(static fn(Worker $value): array => ['id' => $value->getKey(), 'name' => $value->getFullName()])->all(),
            'selected_worker_id' => $worker?->getKey(), 'selected_recipe_id' => $recipeId > 0 ? $recipeId : null,
            'recipes' => $rows, 'history' => $history,
        ]);
    }

    /**
     * Include attempts detached by the one-time catalog replacement through their immutable name snapshot.
     *
     * @return Builder<RecipeTestAttempt>
     */
    private function attemptsForRecipe(User $owner, Worker $worker, Recipe $recipe): Builder
    {
        return RecipeTestAttempt::query()
            ->where('user_id', $owner->getKey())
            ->where('worker_id', $worker->getKey())
            ->where(static function (Builder $query) use ($recipe): void {
                $query->where('recipe_id', $recipe->getKey())
                    ->orWhere(static function (Builder $query) use ($recipe): void {
                        $query->whereNull('recipe_id')->where('recipe_name', $recipe->getName());
                    });
            });
    }
}
