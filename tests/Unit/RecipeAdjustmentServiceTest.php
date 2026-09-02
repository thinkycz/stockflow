<?php

declare(strict_types=1);

use App\Models\RecipeInstruction;
use App\Models\RecipeVariant;
use App\Services\RecipeAdjustmentService;

\test('calculates topping reductions for liquid sugar and flavored syrups with a zero floor', function (): void {
    $variant = RecipeVariant::factory()->createOne();
    foreach ([
        ['liquid sugar', 3, 'ml'],
        ['passion fruit syrup', 8, 'ml'],
        ['strawberry purée', 20, 'ml'],
        ['sweetened condensed milk (Salko)', 10, 'ml'],
        ['granulated sugar', 15, 'g'],
    ] as $position => [$name, $quantity, $unit]) {
        RecipeInstruction::query()->create([
            'recipe_variant_id' => $variant->getKey(),
            'position' => $position + 1,
            'type' => 'ingredient',
            'text' => 'Add ingredient.',
            'action_key' => 'add',
            'quantity_value' => $quantity,
            'quantity_text' => null,
            'unit' => $unit,
            'ingredient_name' => $name,
            'target' => 'shaker',
            'icon_group' => 'syrup_sweetener',
            'source_text' => 'Add ingredient.',
            'is_inferred' => false,
        ]);
    }

    $guidance = (new RecipeAdjustmentService())->forVariant($variant);

    \expect($guidance)->toMatchArray([
        'base_toppings' => '0–1',
        'two_toppings_reduction_ml' => 5,
        'three_toppings_reduction_ml' => 10,
        'components' => [
            ['ingredient_name' => 'liquid sugar', 'unit' => 'ml', 'base_quantity' => 3, 'two_toppings_quantity' => 0, 'three_toppings_quantity' => 0],
            ['ingredient_name' => 'passion fruit syrup', 'unit' => 'ml', 'base_quantity' => 8, 'two_toppings_quantity' => 3, 'three_toppings_quantity' => 0],
        ],
    ]);
});
