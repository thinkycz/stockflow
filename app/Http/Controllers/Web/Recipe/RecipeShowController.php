<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Recipe;

use App\Models\Recipe;
use App\Models\RecipeInstruction;
use App\Models\RecipeVariant;
use App\Models\User;
use App\Services\RecipeAdjustmentService;
use Illuminate\Support\Str;
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
        $recipe->load(['category', 'variants.instructions']);
        $isDrink = Str::upper($recipe->getCategory()->getName()) !== 'PREPARATIONS';
        $adjustmentService = new RecipeAdjustmentService();

        return Inertia::render('recipes/Show', [
            'is_admin' => $actor->isAdmin(),
            'recipe' => [
                'id' => $recipe->getKey(), 'name' => $recipe->getName(), 'note' => $recipe->getNote(),
                'archived' => $recipe->isArchived(),
                'category' => ['id' => $recipe->getCategoryId(), 'name' => $recipe->getCategory()->getName()],
                'variants' => $recipe->getVariants()->map(static function (RecipeVariant $variant) use ($adjustmentService, $isDrink): array {
                    return [
                        'id' => $variant->getKey(), 'name' => $variant->getName(),
                        'topping_adjustments' => $isDrink ? $adjustmentService->forVariant($variant) : null,
                        'instructions' => $variant->getInstructions()->map(static fn(RecipeInstruction $instruction): array => [
                            'id' => $instruction->getKey(), 'type' => $instruction->getType(), 'text' => $instruction->getText(),
                            'action_key' => $instruction->getActionKey(), 'quantity_value' => $instruction->getQuantityValue(),
                            'quantity_text' => $instruction->getQuantityText(), 'unit' => $instruction->getUnit(),
                            'ingredient_name' => $instruction->getIngredientName(), 'target' => $instruction->getTarget(),
                            'icon_group' => $instruction->getIconGroup(),
                        ])->all(),
                    ];
                })->all(),
            ],
        ]);
    }
}
