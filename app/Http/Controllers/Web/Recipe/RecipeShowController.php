<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Recipe;

use App\Models\Recipe;
use App\Models\RecipeStep;
use App\Models\RecipeVariant;
use App\Models\User;
use App\Models\Worker;
use Inertia\Inertia;
use Inertia\Response;

class RecipeShowController
{
    /**
     * Display a recipe and its ordered variants.
     */
    public function __invoke(Recipe $recipe): Response
    {
        $actor = User::mustAuth();
        if (!$actor->isAdmin() && $recipe->isArchived()) {
            \abort(404);
        }
        $recipe->load(['category', 'variants.steps']);
        $workers = [];
        if (!$actor->isAdmin()) {
            $workers = Worker::query()->where('user_id', $actor->resolveScopeUser()->getKey())
                ->orderBy('first_name')->orderBy('last_name')->get()
                ->map(static fn(Worker $worker): array => ['id' => $worker->getKey(), 'name' => $worker->getFullName()])->all();
        }

        return Inertia::render('recipes/Show', [
            'is_admin' => $actor->isAdmin(),
            'recipe' => [
                'id' => $recipe->getKey(), 'name' => $recipe->getName(), 'note' => $recipe->getNote(),
                'archived' => $recipe->isArchived(),
                'category' => ['id' => $recipe->getCategoryId(), 'name' => $recipe->getCategory()->getName()],
                'variants' => $recipe->getVariants()->map(static function (RecipeVariant $variant): array {
                    return [
                        'id' => $variant->getKey(), 'name' => $variant->getName(),
                        'steps' => $variant->getSteps()->map(static fn(RecipeStep $step): array => [
                            'id' => $step->getKey(), 'text' => $step->getText(),
                        ])->all(),
                    ];
                })->all(),
            ],
            'workers' => $workers,
        ]);
    }
}
