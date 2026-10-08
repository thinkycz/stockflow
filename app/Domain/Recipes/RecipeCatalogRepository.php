<?php

declare(strict_types=1);

namespace App\Domain\Recipes;

use Illuminate\Support\Str;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Typer;

/**
 * Read the authored Markdown catalog without database persistence.
 *
 * @phpstan-type CategoryRow array{key: string, name: string, position: int, recipe_count: int}
 * @phpstan-type IngredientRow array{key: string, group: string, name: string, quantity_value: float|int|null, quantity_text: string|null, unit: string|null, icon_group: string}
 * @phpstan-type StepRow array{key: string, title: string, text: string, action_key: string, timer_seconds: int|null}
 * @phpstan-type AdjustmentRow array{ingredient_name: string, unit: string, base_quantity: float|int, two_toppings_quantity: float|int, three_toppings_quantity: float|int}
 * @phpstan-type VariantRow array{key: string, name: string, selectors: array<string, string>, ingredients: list<IngredientRow>, steps: list<StepRow>, tips_html: string, topping_adjustments: list<AdjustmentRow>}
 * @phpstan-type RelatedRow array{key: string, name: string, url: string}
 * @phpstan-type RecipeRow array{key: string, slug: string, name: string, category: CategoryRow, position: int, summary: string, tags: list<string>, aliases: list<string>, equipment: list<string>, variants: list<VariantRow>, notes_html: string, related: list<RelatedRow>, url: string}
 * @phpstan-type LookupRow array{key: string, name: string, category_name: string, category_key: string, aliases: list<string>, keywords: list<string>, url: string}
 */
final class RecipeCatalogRepository
{
    /**
     * @var list<RecipeRow>|null
     */
    private array|null $loaded = null;

    /**
     * Allow isolated authored fixtures while using the application library by default.
     */
    public function __construct(
        /**
         * Alternate catalog directory for isolated validation.
         */
        private readonly string|null $directory = null,
    ) {}

    /**
     * @return list<RecipeRow>
     */
    public function recipes(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }
        $recipes = [];
        foreach ($this->manifest() as $category) {
            $paths = \glob($this->root() . '/' . $category['key'] . '/*.md');
            if ($paths === false || $paths === []) {
                throw new RuntimeException('Recipe category has no documents: ' . $category['key']);
            }
            foreach ($paths as $path) {
                $recipes[] = $this->document($path, $category);
            }
        }
        \usort($recipes, static fn(array $a, array $b): int => [$a['category']['position'], $a['position'], $a['key']] <=> [$b['category']['position'], $b['position'], $b['key']]);
        $keys = \array_column($recipes, 'key');
        if (\count($keys) !== \count(\array_unique($keys))) {
            throw new RuntimeException('Duplicate recipe identities.');
        }
        foreach ($recipes as $recipe) {
            foreach ($recipe['related'] as $related) {
                if (!\in_array($related['key'], $keys, true)) {
                    throw new RuntimeException('Unknown preparation link in ' . $recipe['key'] . ': ' . $related['key']);
                }
            }
        }
        foreach ($recipes as &$recipe) {
            $recipe['category']['recipe_count'] = \count(\array_filter($recipes, static fn(array $candidate): bool => $candidate['category']['key'] === $recipe['category']['key']));
        }
        unset($recipe);

        return $this->loaded = $recipes;
    }

    /**
     * @return list<CategoryRow>
     */
    public function categories(): array
    {
        return \array_map(fn(array $category): array => [
            ...$category,
            'recipe_count' => \count(\array_filter($this->recipes(), static fn(array $recipe): bool => $recipe['category']['key'] === $category['key'])),
        ], $this->manifest());
    }

    /**
     * @return RecipeRow|null
     */
    public function find(string $category, string $slug): array|null
    {
        foreach ($this->recipes() as $recipe) {
            if ($recipe['key'] === $category . '/' . $slug) {
                return $recipe;
            }
        }

        return null;
    }

    /**
     * @return list<LookupRow>
     */
    public function lookupIndex(): array
    {
        return \array_map(static fn(array $recipe): array => [
            'key' => $recipe['key'], 'name' => $recipe['name'], 'category_name' => $recipe['category']['name'],
            'category_key' => $recipe['category']['key'], 'aliases' => $recipe['aliases'],
            'keywords' => [...$recipe['tags'], ...\array_column($recipe['variants'], 'name')], 'url' => $recipe['url'],
        ], $this->recipes());
    }

    /**
     * @return list<CategoryRow>
     */
    private function manifest(): array
    {
        $categories = [];
        foreach (Typer::assertArray(\json_decode($this->read($this->root() . '/catalog.json'), true, flags: \JSON_THROW_ON_ERROR)) as $value) {
            $row = Typer::assertStringKeyArray(Typer::assertArray($value));
            $key = Typer::assertString($row['key'] ?? null);
            $this->assertKey($key);
            $categories[] = ['key' => $key, 'name' => Typer::assertString($row['name'] ?? null), 'position' => Typer::assertInt($row['position'] ?? null), 'recipe_count' => 0];
        }
        if (\count(\array_column($categories, 'key')) !== \count(\array_unique(\array_column($categories, 'key')))) {
            throw new RuntimeException('Duplicate recipe categories.');
        }
        \usort($categories, static fn(array $a, array $b): int => $a['position'] <=> $b['position']);

        return $categories;
    }

    /** @param CategoryRow $category
     * @return RecipeRow
     */
    private function document(string $path, array $category): array
    {
        if (\preg_match('/\\A```json\\R(.*?)\\R```\\R(.*)\\z/s', $this->read($path), $matches) !== 1) {
            throw new RuntimeException('Missing JSON metadata block: ' . $path);
        }
        $metadata = Typer::assertStringKeyArray(Typer::assertArray(\json_decode($matches[1], true, flags: \JSON_THROW_ON_ERROR)));
        $parts = \preg_split('/^## /m', $matches[2]);
        if ($parts === false || \preg_match('/^# (.+)\\R\\R(.+)/s', \mb_trim($parts[0]), $intro) !== 1) {
            throw new RuntimeException('Missing recipe title or description: ' . $path);
        }
        $sections = [];
        foreach (\array_slice($parts, 1) as $part) {
            [$heading, $content] = \array_pad(\explode("\n", $part, 2), 2, '');
            if (isset($sections[\mb_trim($heading)])) {
                throw new RuntimeException('Duplicate heading in ' . $path);
            }
            $sections[\mb_trim($heading)] = \mb_trim($content);
        }
        $variants = [];
        foreach (Typer::assertArray($metadata['variants'] ?? null) as $value) {
            $variant = Typer::assertStringKeyArray(Typer::assertArray($value));
            $name = Typer::assertString($variant['name'] ?? null);
            $key = Typer::assertString($variant['key'] ?? null);
            $this->assertKey($key);
            $content = $sections[$name] ?? throw new RuntimeException('Missing variant ' . $name . ' in ' . $path);
            $ingredients = $this->ingredients($this->subsection($content, 'Ingredients'));
            $actions = \array_values(Typer::assertStringArray(Typer::assertArray($variant['actions'] ?? null)));
            foreach ($actions as $action) {
                if (!\in_array($action, ['add', 'mix', 'stir', 'whisk', 'whip', 'boil', 'heat', 'steam', 'steep', 'ice', 'shake', 'pour', 'smash', 'cook', 'cover', 'timer', 'cool', 'garnish', 'serve', 'wash', 'other'], true)) {
                    throw new RuntimeException('Unknown recipe action in ' . $path . ': ' . $action);
                }
            }
            $timers = Typer::assertArray($variant['timers'] ?? []);
            \preg_match_all('/^(\\d+)\\. \\*\\*(.+?)\\.\\*\\* (.+)$/m', $this->subsection($content, 'Method'), $stepMatches, \PREG_SET_ORDER);
            if (\count($stepMatches) < 2 || \count($stepMatches) !== \count($actions) || $ingredients === []) {
                throw new RuntimeException('Variant needs ingredients and matching ordered actions: ' . $path . ' / ' . $name);
            }
            $steps = [];
            foreach ($stepMatches as $index => $step) {
                if ($step[1] !== (string) ($index + 1)) {
                    throw new RuntimeException('Method steps must be sequential: ' . $path);
                }
                $timer = isset($timers[$index + 1]) ? Typer::assertInt($timers[$index + 1]) : null;
                if ($timer !== null && $timer <= 0) {
                    throw new RuntimeException('Timer must be positive: ' . $path);
                }
                $steps[] = ['key' => 'step-' . ($index + 1), 'title' => $step[2], 'text' => $step[3], 'action_key' => $actions[$index], 'timer_seconds' => $timer];
            }
            foreach (\array_keys($timers) as $timerIndex) {
                if (!\is_int($timerIndex) || $timerIndex < 1 || $timerIndex > \count($steps)) {
                    throw new RuntimeException('Timer references a missing step: ' . $path);
                }
            }
            $variants[] = ['key' => $key, 'name' => $name, 'selectors' => $this->selectors(Typer::assertArray($variant['selectors'] ?? [])),
                'ingredients' => $ingredients, 'steps' => $steps, 'tips_html' => $this->markdown($this->subsection($content, 'Tips')),
                'topping_adjustments' => $category['key'] === 'preparations' ? [] : $this->adjustments($ingredients)];
        }
        if ($variants === [] || \count(\array_column($variants, 'key')) !== \count(\array_unique(\array_column($variants, 'key')))) {
            throw new RuntimeException('Missing or duplicate variants: ' . $path);
        }
        foreach (\array_keys($sections) as $sectionHeading) {
            if (!\in_array($sectionHeading, ['Equipment', 'Notes', 'Related preparations', ...\array_column($variants, 'name')], true)) {
                throw new RuntimeException('Unknown recipe heading in ' . $path . ': ' . $sectionHeading);
            }
        }
        if ($this->bullets($sections['Equipment'] ?? '') === []) {
            throw new RuntimeException('Recipe equipment is required: ' . $path);
        }
        $related = [];
        \preg_match_all('~^- \\[([^]]+)\\]\\(/recipes/([a-z0-9-]+/[a-z0-9-]+)\\)$~m', $sections['Related preparations'] ?? '', $links, \PREG_SET_ORDER);
        foreach ($links as $link) {
            $related[] = ['key' => $link[2], 'name' => $link[1], 'url' => '/recipes/' . $link[2]];
        }
        $slug = \pathinfo($path, \PATHINFO_FILENAME);
        $this->assertKey($slug);

        return ['key' => $category['key'] . '/' . $slug, 'slug' => $slug, 'name' => $intro[1], 'category' => $category,
            'position' => Typer::assertInt($metadata['position'] ?? null), 'summary' => \mb_trim($intro[2]),
            'tags' => \array_values(Typer::assertStringArray(Typer::assertArray($metadata['tags'] ?? []))),
            'aliases' => \array_values(Typer::assertStringArray(Typer::assertArray($metadata['aliases'] ?? []))),
            'equipment' => $this->bullets($sections['Equipment'] ?? ''), 'variants' => $variants,
            'notes_html' => $this->markdown($sections['Notes'] ?? ''), 'related' => $related,
            'url' => '/recipes/' . $category['key'] . '/' . $slug];
    }

    /**
     * @return list<IngredientRow>
     */
    private function ingredients(string $content): array
    {
        $rows = [];
        $lines = \explode("\n", \mb_trim($content));
        if (\preg_replace('/\\s+/', '', $lines[0]) !== '|Component|Ingredient|Amount|Unit|') {
            throw new RuntimeException('Missing ingredient table header.');
        }
        foreach (\array_slice($lines, 2) as $line) {
            $cells = \array_map(\mb_trim(...), \explode('|', \mb_trim($line, " \t\r|")));
            if (\count($cells) !== 4 || $cells[0] === '' || $cells[1] === '' || $cells[2] === '') {
                throw new RuntimeException('Ingredients require Component, Ingredient, Amount, and Unit columns.');
            }
            $number = \is_numeric($cells[2]) ? (float) $cells[2] : null;
            if ($number !== null && $number <= 0) {
                throw new RuntimeException('Ingredient quantities must be positive.');
            }
            if (!\in_array($cells[3], ['g', 'kg', 'ml', 'L', 'pieces', 'scoops', '—'], true)) {
                throw new RuntimeException('Unknown ingredient unit: ' . $cells[3]);
            }
            $rows[] = ['key' => 'ingredient-' . (\count($rows) + 1), 'group' => $cells[0], 'name' => $cells[1],
                'quantity_value' => $number === null ? null : ($number === \floor($number) ? (int) $number : $number),
                'quantity_text' => $number === null ? $cells[2] : null, 'unit' => $cells[3] === '—' ? null : $cells[3],
                'icon_group' => $this->iconGroup($cells[1])];
        }

        return $rows;
    }

    /** @param list<IngredientRow> $ingredients
     * @return list<AdjustmentRow>
     */
    private function adjustments(array $ingredients): array
    {
        $rows = [];
        foreach ($ingredients as $ingredient) {
            if ($ingredient['unit'] !== 'ml' || $ingredient['quantity_value'] === null || ($ingredient['name'] !== 'liquid sugar' && !\str_ends_with($ingredient['name'], ' syrup'))) {
                continue;
            }
            $rows[] = ['ingredient_name' => $ingredient['name'], 'unit' => 'ml', 'base_quantity' => $ingredient['quantity_value'],
                'two_toppings_quantity' => \max(0, $ingredient['quantity_value'] - 5), 'three_toppings_quantity' => \max(0, $ingredient['quantity_value'] - 10)];
        }

        return $rows;
    }

    /**
     * Map consistent ingredient names to the shared recipe icon families.
     */
    private function iconGroup(string $name): string
    {
        $value = Str::lower($name);
        if (\in_array($value, ['ice', 'ice cubes'], true)) {
            return 'ice';
        }
        foreach (['topping_garnish' => ['dried', 'flakes', 'crumbs', 'chopped'], 'syrup_sweetener' => ['sugar', 'syrup'], 'tea_matcha' => ['tea', 'matcha', 'hojicha'], 'powder' => ['powder', 'tapioca', 'paste'], 'milk_foam' => ['cream', 'condensed'], 'water_milk' => ['milk', 'water'], 'fruit' => ['fruit', 'mango', 'strawber', 'lychee', 'lychees', 'lemon', 'peach', 'orange', 'pineapple', 'lemongrass']] as $group => $terms) {
            if (Str::contains($value, $terms)) {
                return $group;
            }
        }

        return 'neutral';
    }

    /**
     * Extract one named variant subsection without consuming the next heading.
     */
    private function subsection(string $content, string $heading): string
    {
        return \preg_match('/^### ' . \preg_quote($heading, '/') . '\\R(.*?)(?=^### |\\z)/ms', $content, $matches) === 1 ? \mb_trim($matches[1]) : '';
    }

    /**
     * @return list<string>
     */
    private function bullets(string $content): array
    {
        \preg_match_all('/^- (.+)$/m', $content, $matches);

        return $matches[1];
    }

    /** @param array<array-key, mixed> $values
     * @return array<string, string>
     */
    private function selectors(array $values): array
    {
        $selectors = [];
        foreach ($values as $key => $value) {
            $dimension = Typer::assertString($key);
            if (!\in_array($dimension, ['size', 'ice', 'flavour', 'batch', 'temperature'], true)) {
                throw new RuntimeException('Unknown recipe selector: ' . $dimension);
            }
            $selectors[$dimension] = Typer::assertString($value);
        }

        return $selectors;
    }

    /**
     * Render authored notes with raw HTML and unsafe links disabled.
     */
    private function markdown(string $text): string
    {
        return $text === '' ? '' : Str::markdown($text, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    /**
     * Require a stable path-safe category, recipe, or variant key.
     */
    private function assertKey(string $key): void
    {
        if (\preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $key) !== 1) {
            throw new RuntimeException('Invalid catalog key: ' . $key);
        }
    }

    /**
     * Read one known catalog source file.
     */
    private function read(string $path): string
    {
        return Typer::assertString(\file_get_contents($path));
    }

    /**
     * Resolve the fixed authored catalog directory.
     */
    private function root(): string
    {
        return $this->directory ?? \resource_path('recipes');
    }
}
