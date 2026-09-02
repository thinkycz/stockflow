<?php

declare(strict_types=1);

use App\Support\RecipeDefaultCatalog;
use App\Support\RecipeTextParser;

/**
 * @return array{name: string, note: string|null, variants: list<array{name: string|null, instructions: list<array<string, mixed>>}>}
 */
function catalogRecipe(string $name): array
{
    foreach (RecipeDefaultCatalog::categories() as $category) {
        foreach ($category['recipes'] as $recipe) {
            if ($recipe['name'] === $name) {
                return $recipe;
            }
        }
    }

    throw new RuntimeException('Missing recipe: ' . $name);
}

\test('canonical catalog has the reviewed category recipe and variant totals', function (): void {
    $categories = RecipeDefaultCatalog::categories();
    $recipes = \collect($categories)->flatMap(static fn(array $category): array => $category['recipes']);
    $variants = $recipes->flatMap(static fn(array $recipe): array => $recipe['variants']);

    \expect($categories)->toHaveCount(8)
        ->and($recipes)->toHaveCount(49)
        ->and($variants)->toHaveCount(184)
        ->and($variants->filter(static fn(array $variant): bool => \str_ends_with((string) $variant['name'], '— With ice')))->toHaveCount(85)
        ->and($variants->filter(static fn(array $variant): bool => \str_ends_with((string) $variant['name'], '— No ice')))->toHaveCount(85);
});

\test('every canonical instruction is explicit normalized and testable', function (): void {
    foreach (RecipeDefaultCatalog::categories() as $category) {
        foreach ($category['recipes'] as $recipe) {
            \expect($recipe['name'])->not->toMatch('/[()\\[\\]]/');
            foreach ($recipe['variants'] as $variant) {
                \expect($variant['instructions'])->toHaveCount(\count($variant['instructions']))
                    ->and(\count($variant['instructions']))->toBeGreaterThanOrEqual(2)
                    ->and($variant['name'] ?? '')->not->toMatch('/[()\\[\\]]/');

                $texts = [];
                foreach ($variant['instructions'] as $instruction) {
                    \expect($instruction['type'])->toBeIn(['ingredient', 'action'])
                        ->and($instruction['text'])->toBeString()->not->toBe('')
                        ->and($instruction['action_key'])->toBeIn(RecipeTextParser::ACTION_KEYS)
                        ->and($instruction['icon_group'])->toBeIn(RecipeTextParser::ICON_GROUPS)
                        ->and($instruction['is_inferred'])->toBeFalse()
                        ->and($instruction['text'])->not->toMatch('/\\b(?:BS|PF|DF|sp)\\b|smetana|\\[[^]]*]|\\b\\d+(?:[.,]\\d+)?(?:ml|g|L|kg)\\b|\\d,\\d/u')
                        ->and($instruction['unit'])->toBeIn([null, 'ml', 'L', 'g', 'kg', 'standard scoop', 'standard scoops']);
                    $texts[] = $instruction['text'];

                    if ($instruction['type'] === 'ingredient') {
                        \expect($instruction['ingredient_name'])->toBeString()->not->toBe('')
                            ->and($instruction['target'])->toBeString()->not->toBe('');
                        if ($instruction['quantity_value'] !== null && \preg_match('/^(?:liquid sugar|milk|oat milk|coconut milk|fresh milk|whipping cream|sweetened condensed milk \\(Salko\\)|coconut water|.* purée|.* syrup|hot water|water at .*)$/u', (string) $instruction['ingredient_name']) === 1) {
                            \expect($instruction['unit'])->toBeIn(['ml', 'L']);
                        }
                    } else {
                        \expect($instruction['quantity_value'])->toBeNull()
                            ->and($instruction['quantity_text'])->toBeNull()
                            ->and($instruction['unit'])->toBeNull()
                            ->and($instruction['ingredient_name'])->toBeNull();
                    }
                }

                \expect($texts)->toBe(\array_values(\array_unique($texts)));
            }
        }
    }
});

\test('made to order drinks have paired ice modes and explicit no ice adjustments', function (): void {
    foreach (\array_slice(RecipeDefaultCatalog::categories(), 0, 7) as $category) {
        foreach ($category['recipes'] as $recipe) {
            $names = \array_column($recipe['variants'], 'name');
            $hasSmall = \collect($names)->contains(static fn(string|null $name): bool => \preg_match('/(?:^| — )S — With ice$/', (string) $name) === 1);
            $hasLarge = \collect($names)->contains(static fn(string|null $name): bool => \preg_match('/(?:^| — )L — With ice$/', (string) $name) === 1);
            foreach ($names as $name) {
                $label = (string) $name;
                \expect($label)->toEndWith(\str_ends_with($label, '— With ice') ? '— With ice' : '— No ice');
                $paired = \str_replace(['— With ice', '— No ice'], '', $label);
                \expect($names)->toContain($paired . (\str_ends_with($label, '— With ice') ? '— No ice' : '— With ice'));
            }

            foreach ($recipe['variants'] as $variant) {
                if (!\str_ends_with((string) $variant['name'], '— No ice')) {
                    continue;
                }
                $withIceName = \str_replace('— No ice', '— With ice', (string) $variant['name']);
                $withIce = \collect($recipe['variants'])->firstWhere('name', $withIceName);
                $size = \preg_match('/(?:^| — )([SML]) — No ice$/', (string) $variant['name'], $matches) === 1 ? $matches[1] : '';
                $expectedIncrease = $hasSmall ? ($size === 'S' ? 5 : 10) : ($hasLarge ? ($size === 'M' ? 5 : 10) : 5);
                $baseSugar = \collect($withIce['instructions'])->firstWhere('ingredient_name', 'liquid sugar')['quantity_value'] ?? 0;
                $noIceSugar = \collect($variant['instructions'])->firstWhere('ingredient_name', 'liquid sugar')['quantity_value'] ?? null;
                \expect(\collect($variant['instructions'])->contains(static fn(array $instruction): bool => $instruction['ingredient_name'] === 'ice cubes' && $instruction['quantity_text'] === '2–3'))->toBeTrue();
                \expect($noIceSugar)->toBe($baseSugar + $expectedIncrease);
            }
        }
    }

    $classic = \catalogRecipe('CLASSIC MATCHA LATTE');
    \expect(\collect($classic['variants'][1]['instructions'])->firstWhere('ingredient_name', 'milk')['quantity_value'])->toBe(150)
        ->and(\collect($classic['variants'][3]['instructions'])->firstWhere('ingredient_name', 'milk')['quantity_value'])->toBe(240);

    $coconut = \catalogRecipe('COCONUT CLOUD');
    \expect(\collect($coconut['variants'][1]['instructions'])->firstWhere('ingredient_name', 'coconut water')['quantity_value'])->toBe(150)
        ->and(\collect($coconut['variants'][3]['instructions'])->firstWhere('ingredient_name', 'coconut water')['quantity_value'])->toBe(200);

    $coldWhisk = \catalogRecipe('COLD WHISK OAT LATTE');
    $coldWhiskNoIce = \array_column($coldWhisk['variants'][1]['instructions'], 'text');
    \expect(\array_search('Top up with oat milk to the standard serving line.', $coldWhiskNoIce, true))
        ->toBeGreaterThan(\array_search('Pour the whipped latte into the serving cup.', $coldWhiskNoIce, true));
});

\test('highlighted recipe defects are resolved in structured metadata', function (): void {
    $lychee = \catalogRecipe('LYCHEE TEA');
    $large = \collect($lychee['variants'])->firstWhere('name', 'L — With ice');
    \expect($large)->not->toBeNull()
        ->and(\collect($large['instructions'])->firstWhere('ingredient_name', 'lychee purée')['quantity_value'])->toBe(40)
        ->and(\collect($large['instructions'])->firstWhere('ingredient_name', 'lemon syrup')['quantity_value'])->toBe(5);

    $tapioca = \catalogRecipe('BLACK TAPIOCA');
    foreach ([[500, 150, 100, 30], [700, 210, 150, 40], [1000, 300, 200, 50]] as $index => [$amount, $sugar, $water, $syrup]) {
        $ingredients = \collect($tapioca['variants'][$index]['instructions'])->where('type', 'ingredient')->values();
        \expect($ingredients->firstWhere('ingredient_name', 'black tapioca')['quantity_value'])->toBe($amount === 1000 ? 1 : $amount)
            ->and($ingredients->firstWhere('ingredient_name', 'granulated sugar')['quantity_value'])->toBe($sugar)
            ->and($ingredients->where('ingredient_name', 'water')->last()['quantity_value'])->toBe($water)
            ->and($ingredients->firstWhere('ingredient_name', 'brown sugar syrup')['quantity_value'])->toBe($syrup)
            ->and($ingredients->where('ingredient_name', 'water')->first()['quantity_value'])->toBe(4);
    }

    $ceylon = \catalogRecipe('CEYLON MILK TEA PREPARATION');
    $ceylonIngredients = \collect($ceylon['variants'][0]['instructions']);
    \expect($ceylonIngredients->firstWhere('ingredient_name', 'Ceylon tea')['quantity_value'])->toBe(60)
        ->and($ceylonIngredients->firstWhere('ingredient_name', 'Yunnan tea')['quantity_value'])->toBe(30)
        ->and($ceylonIngredients->firstWhere('ingredient_name', 'oolong tea')['quantity_value'])->toBe(10);

    $oolong = \catalogRecipe('OOLONG MILK TEA PREPARATION');
    \expect(\array_column($oolong['variants'], 'name'))->toBe(['3.5 L batch', '1.5 L batch']);
});

\test('grouped recipes expose explicit base flavor size and ice variants', function (): void {
    \expect(\catalogRecipe('MANGO/STRAWBERRY MATCHA LATTE')['variants'])->toHaveCount(8)
        ->and(\catalogRecipe('CEYLON/JASMINE/OOLONG MILK TEA')['variants'])->toHaveCount(12)
        ->and(\catalogRecipe('BROWN SUGAR MILK TEA/FRESH MILK')['variants'])->toHaveCount(8)
        ->and(\array_column(\catalogRecipe('DOUBLE STRAWBERRY')['variants'], 'name'))->toBe(['M — With ice', 'M — No ice']);
});
