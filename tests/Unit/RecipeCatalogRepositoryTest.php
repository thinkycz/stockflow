<?php

declare(strict_types=1);

use App\Domain\Recipes\RecipeCatalogRepository;
use Thinkycz\LaravelCore\Support\Typer;

\test('the catalog preserves reviewed measurements and actions with approved formula corrections', function (): void {
    $catalog = new RecipeCatalogRepository();
    $recipes = $catalog->recipes();
    $signatures = Typer::assertArray(\json_decode(Typer::assertString(\file_get_contents(\base_path('tests/Fixtures/recipe-measurement-signatures.json'))), true, flags: \JSON_THROW_ON_ERROR));
    \expect($recipes)->toHaveCount(54)
        ->and($catalog->categories())->toHaveCount(9)
        ->and(\array_sum(\array_map(static fn(array $recipe): int => \count($recipe['variants']), $recipes)))->toBe(189)
        ->and($signatures)->toHaveCount(184);

    foreach ($signatures as $value) {
        $expected = Typer::assertStringKeyArray(Typer::assertArray($value));
        [$category, $slug] = \explode('/', Typer::assertString($expected['recipe']));
        $recipe = $catalog->find($category, $slug) ?? throw new RuntimeException('Missing reviewed recipe: ' . $category . '/' . $slug);
        $variant = \array_find($recipe['variants'], static fn(array $row): bool => $row['key'] === $expected['variant']) ?? throw new RuntimeException('Missing reviewed variant.');
        $ingredients = \array_map(static fn(array $row): array => [$row['name'] === 'hojicha powder' && $row['quantity_value'] !== null ? 'hojicha' : $row['name'], $row['quantity_value'], $row['quantity_text'], $row['unit']], \array_values(\array_filter($variant['ingredients'], static fn(array $row): bool => !\in_array($row['group'], ['Ice', 'Batch dilution', 'Milk to volume', 'Top-up', 'Rinsing'], true))));
        $steps = $variant['steps'];
        if (isset($expected['top_up_original_index'])) {
            $index = \array_find_key($steps, static fn(array $step): bool => \str_starts_with($step['text'], 'Top up with ')) ?? throw new RuntimeException('Missing original top-up action.');
            $topUp = $steps[$index];
            \array_splice($steps, $index, 1);
            \array_splice($steps, Typer::assertInt($expected['top_up_original_index']), 0, [$topUp]);
        }
        \expect(\hash('sha256', \json_encode($ingredients, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)))->toBe($expected['ingredients_sha256'])
            ->and(\hash('sha256', \json_encode(\array_column($steps, 'action_key'), \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)))->toBe($expected['actions_sha256']);
    }
});

\test('the five hot drinks retain weights standard scoops and the counted strawberry garnish', function (): void {
    $catalog = new RecipeCatalogRepository();
    $expected = [
        'classic-matcha' => [['milk', 200, 'g'], ['water at 70–80 °C', 50, 'g'], ['Himawari matcha', 3.5, 'g']],
        'strawberry-cloud' => [['milk', 140, 'g'], ['water at 70–80 °C', 50, 'g'], ['Himawari matcha', 3.5, 'g'], ['whipping cream', 30, 'g'], ['milk', 30, 'g'], ['strawberry syrup', 30, 'g'], ['dried strawberries', 2, 'pieces']],
        'banana-bread-matcha' => [['gingerbread syrup', 5, 'g'], ['banana milk', 200, 'g'], ['water at 70–80 °C', 50, 'g'], ['Himawari matcha', 3.5, 'g']],
        'ceylon-milk-tea' => [['prepared Ceylon milk tea', 300, 'g'], ['granulated sugar', 10, 'g']],
        'taro-milk-tea' => [['hot water', 200, 'g'], ['milk powder', 3, 'scoops'], ['taro powder', 2, 'scoops'], ['granulated sugar', 5, 'g'], ['milk', 100, 'g']],
    ];
    foreach ($expected as $slug => $ingredients) {
        $recipe = $catalog->find('hot-drinks', $slug) ?? throw new RuntimeException('Missing hot drink.');
        \expect(\array_map(static fn(array $row): array => [$row['name'], $row['quantity_value'], $row['unit']], $recipe['variants'][0]['ingredients']))->toBe($ingredients)
            ->and($recipe['variants'][0]['topping_adjustments'])->toBe([]);
    }
    $taro = $catalog->find('hot-drinks', 'taro-milk-tea') ?? throw new RuntimeException('Missing taro.');
    \expect(\array_column($taro['variants'][0]['steps'], 'action_key'))->toBe(['add', 'add', 'add', 'add', 'mix', 'add', 'mix', 'pour']);
});

\test('ice variants keep their own quantities chilling rules and separate topping guidance', function (): void {
    $recipe = (new RecipeCatalogRepository())->find('matcha-latte', 'classic-matcha-latte') ?? throw new RuntimeException('Missing matcha.');
    $iced = $recipe['variants'][0];
    $noIce = $recipe['variants'][1];
    \expect($iced['topping_adjustments'][0])->toMatchArray(['base_quantity' => 20, 'two_toppings_quantity' => 15, 'three_toppings_quantity' => 10])
        ->and($noIce['topping_adjustments'][0]['base_quantity'])->toBe(25)
        ->and($noIce['ingredients'][0]['quantity_value'])->toBe(150)
        ->and(\array_column($noIce['steps'], 'text'))->toContain('Add 2–3 ice cubes to the serving cup for chilling.')
        ->and($noIce['tips_html'])->toContain('2–3 ice cubes');
});

\test('all ice used by a method is explicitly listed in the ingredients', function (): void {
    foreach ((new RecipeCatalogRepository())->recipes() as $recipe) {
        foreach ($recipe['variants'] as $variant) {
            if (\array_any($variant['steps'], static fn(array $step): bool => $step['action_key'] === 'ice')) {
                \expect(\array_find($variant['ingredients'], static fn(array $ingredient): bool => $ingredient['name'] === 'ice cubes'))->not->toBeNull();
            }
            if (($variant['selectors']['ice'] ?? null) === 'with-ice') {
                $ice = \array_find($variant['ingredients'], static fn(array $ingredient): bool => $ingredient['name'] === 'ice cubes');
                \expect($ice['quantity_text'] ?? null)->toBe('full serving cup');
            }
        }
    }
});

\test('milk added to reach the taro mixture volume is listed separately from any final top-up', function (): void {
    foreach (['taro-milk-tea' => 'milk', 'taro-coco-milk-tea' => 'coconut milk'] as $slug => $milk) {
        $recipe = (new RecipeCatalogRepository())->find('milk-tea', $slug) ?? throw new RuntimeException('Missing taro.');
        foreach ($recipe['variants'] as $variant) {
            $volume = $variant['selectors']['size'] === 'M' ? 300 : 400;
            \expect(\array_find($variant['ingredients'], static fn(array $ingredient): bool => $ingredient['name'] === $milk && $ingredient['quantity_text'] === 'to ' . $volume . ' ml total mixture'))->not->toBeNull();
        }
    }
});

\test('final top-ups follow base assembly and leave room for cloud toppings', function (): void {
    foreach ((new RecipeCatalogRepository())->recipes() as $recipe) {
        foreach ($recipe['variants'] as $variant) {
            $topUp = \array_find_key($variant['steps'], static fn(array $step): bool => \str_starts_with($step['text'], 'Top up with '));
            if ($topUp === null) {
                continue;
            }
            \expect(\array_any($variant['ingredients'], static fn(array $ingredient): bool => $ingredient['group'] === 'Top-up'))->toBeTrue();
            foreach ($variant['steps'] as $index => $step) {
                if ($step['action_key'] !== 'pour') {
                    continue;
                }
                if (\str_contains($step['text'], 'cloud')) {
                    \expect($index)->toBeGreaterThan($topUp)
                        ->and($variant['steps'][$topUp]['text'])->toContain('leaving room for the cloud');
                } else {
                    \expect($topUp)->toBeGreaterThan($index);
                }
            }
        }
    }
});

\test('fruit slices use fruit icons while ice cubes use ice icons', function (): void {
    $recipe = (new RecipeCatalogRepository())->find('fresh-fruit-tea', 'vietnamese-peach-ice-tea') ?? throw new RuntimeException('Missing fruit tea.');
    foreach ($recipe['variants'] as $variant) {
        foreach ($variant['ingredients'] as $ingredient) {
            if (\in_array($ingredient['name'], ['peach slice', 'orange slice'], true)) {
                \expect($ingredient['icon_group'])->toBe('fruit');
            }
            if ($ingredient['name'] === 'ice cubes') {
                \expect($ingredient['icon_group'])->toBe('ice');
            }
        }
    }
});

\test('hojicha is whipped directly into the cloud and the cheese portion scales from its batch', function (): void {
    $catalog = new RecipeCatalogRepository();
    $hojicha = $catalog->find('matcha-specials', 'hojicha-cloud') ?? throw new RuntimeException('Missing hojicha.');
    foreach ($hojicha['variants'] as $variant) {
        \expect(\array_any($variant['ingredients'], static fn(array $row): bool => $row['name'] === 'hojicha powder' && $row['quantity_value'] !== null))->toBeTrue()
            ->and(\array_any($variant['steps'], static fn(array $row): bool => \str_contains($row['text'], 'water')))->toBeFalse();
    }
    $cheese = $catalog->find('preparations', 'cream-cheese') ?? throw new RuntimeException('Missing cheese.');
    $batch = [];
    foreach ($cheese['variants'][0]['ingredients'] as $ingredient) {
        $batch[$ingredient['name']] = $ingredient['quantity_value'];
    }
    foreach ($cheese['variants'][1]['ingredients'] as $ingredient) {
        \expect($ingredient['quantity_value'] * 5)->toEqual($batch[$ingredient['name']]);
    }
    $butterfly = $catalog->find('preparations', 'butterfly-tea') ?? throw new RuntimeException('Missing butterfly.');
    \expect($butterfly['variants'][0]['steps'][2]['text'])->toContain('deep, dark blue')
        ->and($butterfly['variants'][0]['steps'][2]['timer_seconds'])->toBeNull();
});

\test('tea bags are immersed and removed with the correct batch count and tapioca is drained', function (): void {
    $catalog = new RecipeCatalogRepository();
    $oolong = $catalog->find('preparations', 'oolong-milk-tea-preparation') ?? throw new RuntimeException('Missing oolong.');
    \expect($oolong['variants'][0]['steps'][2]['text'])->toContain('two filled tea bags')
        ->and($oolong['variants'][0]['steps'][3]['text'])->toContain('Remove both tea bags')
        ->and($oolong['variants'][1]['steps'][2]['text'])->toContain('Immerse the filled tea bag')
        ->and($oolong['variants'][1]['steps'][3]['text'])->toContain('Remove the tea bag');
    foreach ($catalog->recipes() as $recipe) {
        if ($recipe['category']['key'] !== 'preparations' || !\in_array('tea', $recipe['tags'], true)) {
            continue;
        }
        foreach ($recipe['variants'] as $variant) {
            $removal = \array_find_key($variant['steps'], static fn(array $step): bool => \str_contains($step['text'], 'Remove ') || \str_contains($step['text'], 'strain out') || \str_contains($step['text'], 'Strain out'));
            $ice = \array_find_key($variant['steps'], static fn(array $step): bool => $step['action_key'] === 'ice');
            \expect($removal)->not->toBeNull()->and($ice)->not->toBeNull()->and($removal)->toBeLessThan($ice);
        }
    }
    $tapioca = $catalog->find('preparations', 'black-tapioca') ?? throw new RuntimeException('Missing tapioca.');
    foreach ($tapioca['variants'] as $variant) {
        \expect($variant['steps'][6]['text'])->toContain('Drain away the cooking water')->toContain('empty pot');
    }
});

\test('file identities disambiguate hot and cold names and reject path traversal', function (): void {
    $catalog = new RecipeCatalogRepository();
    \expect($catalog->find('hot-drinks', 'strawberry-cloud')['key'] ?? null)->toBe('hot-drinks/strawberry-cloud')
        ->and($catalog->find('matcha-specials', 'strawberry-cloud')['key'] ?? null)->toBe('matcha-specials/strawberry-cloud')
        ->and($catalog->find('../hot-drinks', 'classic-matcha'))->toBeNull()
        ->and($catalog->find('hot-drinks', '../../catalog'))->toBeNull();
});

\test('malformed authored methods and preparation links fail clearly', function (string $invalid): void {
    $directory = \sys_get_temp_dir() . '/stockflow-recipe-' . \bin2hex(\random_bytes(8));
    \mkdir($directory . '/hot-drinks', 0o777, true);
    \file_put_contents($directory . '/catalog.json', '[{"key":"hot-drinks","name":"HOT DRINKS","position":1}]');
    $document = Typer::assertString(\file_get_contents(\resource_path('recipes/hot-drinks/classic-matcha.md')));
    $document = match ($invalid) {
        'timer' => \str_replace('"timers": {}', '"timers": {"99": 60}', $document),
        'method' => \str_replace('2. **Steam', '9. **Steam', $document),
        'link' => $document . "\n## Related preparations\n\n- [Missing tea](/recipes/preparations/missing-tea)\n",
        'action' => \str_replace('"steam"', '"unknown"', $document),
        'unit' => \preg_replace('/\\| g(\\s*\\|)/', '| invalid$1', $document),
    };
    \file_put_contents($directory . '/hot-drinks/classic-matcha.md', $document);
    try {
        \expect(fn() => (new RecipeCatalogRepository($directory))->recipes())->toThrow(RuntimeException::class);
    } finally {
        \unlink($directory . '/hot-drinks/classic-matcha.md');
        \unlink($directory . '/catalog.json');
        \rmdir($directory . '/hot-drinks');
        \rmdir($directory);
    }
})->with(['timer', 'method', 'link', 'action', 'unit']);
