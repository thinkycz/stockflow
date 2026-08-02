<?php

declare(strict_types=1);

use App\Support\RecipeTextParser;

\test('splits a combined ingredient line and procedure action', function (): void {
    $parsed = (new RecipeTextParser())->parse('100g milk + 20g sugar - stir');

    \expect($parsed['ingredients'])->toMatchArray([
        ['quantity_value' => 100, 'quantity_text' => null, 'unit' => 'g', 'name' => 'milk', 'icon_group' => 'water_milk', 'source_text' => '100g milk'],
        ['quantity_value' => 20, 'quantity_text' => null, 'unit' => 'g', 'name' => 'sugar', 'icon_group' => 'syrup_sweetener', 'source_text' => '20g sugar'],
    ])->and($parsed['steps'])->toBe([
        ['text' => 'stir', 'action_key' => 'stir', 'source_text' => '100g milk + 20g sugar - stir'],
    ]);
});

\test('preserves nonstandard quantity expressions alongside normalized numbers', function (): void {
    $parser = new RecipeTextParser();

    $decimal = $parser->parse('1,5 spoons of strawberries')['ingredients'][0];
    $fallback = $parser->parse('half PF/mango')['ingredients'][0];
    $few = $parser->parse('A few strawberries + 1 lychee')['ingredients'];

    \expect($decimal['quantity_value'])->toBe(1.5)
        ->and($decimal['quantity_text'])->toBe('1,5')
        ->and($decimal['unit'])->toBe('spoons')
        ->and($fallback['quantity_value'])->toBeNull()
        ->and($fallback['quantity_text'])->toBe('half')
        ->and($few[0]['quantity_text'])->toBe('A few')
        ->and($few[1]['quantity_value'])->toBe(1);
});

\test('keeps ambiguous source wording in a fallback ingredient and action step', function (): void {
    $parsed = (new RecipeTextParser())->parse('Cloud: mystery topping');

    \expect($parsed['ingredients'][0]['name'])->toBe('mystery topping')
        ->and($parsed['ingredients'][0]['icon_group'])->toBe('topping_garnish')
        ->and($parsed['ingredients'][0]['source_text'])->toBe('mystery topping')
        ->and($parsed['steps'][0]['action_key'])->toBe('garnish');
});

\test('keeps unambiguous procedural lines out of the ingredient list', function (): void {
    $parser = new RecipeTextParser();

    \expect($parser->parse('set a timer for 10min')['ingredients'])->toBe([])
        ->and($parser->parse('ice up to 4l')['ingredients'])->toBe([])
        ->and($parser->parse('fill up with ice')['ingredients'][0]['name'] ?? null)->toBe('ice')
        ->and($parser->parse('set a timer for 10min')['steps'][0]['action_key'])->toBe('timer');
});
