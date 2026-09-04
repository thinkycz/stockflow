<?php

declare(strict_types=1);

namespace App\Domain\Recipes;

use App\Models\RecipeInstruction;
use App\Models\RecipeVariant;
use Illuminate\Support\Str;
use Thinkycz\LaravelCore\Support\Typer;

/**
 * Calculate the non-testable topping guidance shown alongside drink instructions.
 *
 * @phpstan-type AdjustmentComponent array{ingredient_name: string, unit: 'ml', base_quantity: float|int, two_toppings_quantity: float|int, three_toppings_quantity: float|int}
 * @phpstan-type AdjustmentGuidance array{base_toppings: '0–1', two_toppings_reduction_ml: 5, three_toppings_reduction_ml: 10, components: list<AdjustmentComponent>}
 */
final class RecipeAdjustmentService
{
    /**
     * Calculate topping adjustments for one recipe variant.
     *
     * @return AdjustmentGuidance
     */
    public function forVariant(RecipeVariant $variant): array
    {
        $components = [];
        foreach ($variant->getInstructions() as $value) {
            $instruction = Typer::assertInstance($value, RecipeInstruction::class);
            $ingredientName = $instruction->getIngredientName();
            $quantity = $instruction->getQuantityValue();
            if ($instruction->getType() !== 'ingredient' || $instruction->getUnit() !== 'ml' || $ingredientName === null || $quantity === null) {
                continue;
            }

            $normalizedName = Str::lower($ingredientName);
            if ($normalizedName !== 'liquid sugar' && !Str::endsWith($normalizedName, ' syrup')) {
                continue;
            }

            $components[] = [
                'ingredient_name' => $ingredientName,
                'unit' => 'ml',
                'base_quantity' => $quantity,
                'two_toppings_quantity' => \max(0, $quantity - 5),
                'three_toppings_quantity' => \max(0, $quantity - 10),
            ];
        }

        return [
            'base_toppings' => '0–1',
            'two_toppings_reduction_ml' => 5,
            'three_toppings_reduction_ml' => 10,
            'components' => $components,
        ];
    }
}
