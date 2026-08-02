<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

class RecipeTextParser
{
    /**
     * Ingredient icon taxonomy used by the importer and editor defaults.
     *
     * @var list<string>
     */
    public const array ICON_GROUPS = ['water_milk', 'tea_matcha', 'fruit', 'syrup_sweetener', 'powder', 'milk_foam', 'ice', 'topping_garnish', 'neutral'];

    /**
     * Action keys used by procedure steps and their icons.
     *
     * @var list<string>
     */
    public const array ACTION_KEYS = ['add', 'mix', 'stir', 'whisk', 'whip', 'boil', 'steep', 'ice', 'shake', 'pour', 'smash', 'cook', 'cover', 'timer', 'cool', 'garnish', 'serve', 'wash', 'other'];

    /**
     * Split one legacy source line into structured ingredients and one procedure step.
     *
     * @return array{ingredients: list<array{quantity_value: float|int|null, quantity_text: string|null, unit: string|null, name: string, icon_group: string, source_text: string}>, steps: list<array{text: string, action_key: string, source_text: string}>}
     */
    public function parse(string $source): array
    {
        $original = \mb_trim($source);
        if ($original === '') {
            return ['ingredients' => [], 'steps' => []];
        }

        [$ingredientText, $suffix] = $this->splitActionSuffix($original);
        $ingredientFragments = $this->ingredientFragments($ingredientText, $original);
        $ingredients = [];
        foreach ($ingredientFragments as $fragment) {
            $ingredient = $this->parseIngredient($fragment);
            if ($ingredient !== null) {
                $ingredients[] = $ingredient;
            }
        }

        $actionKey = $this->actionKey($original, $suffix, $ingredients !== []);
        $stepText = $this->stepText($original, $ingredientText, $suffix, $actionKey, $ingredients !== []);

        return [
            'ingredients' => $ingredients,
            'steps' => [['text' => $stepText, 'action_key' => $actionKey, 'source_text' => $original]],
        ];
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function splitActionSuffix(string $source): array
    {
        $parts = \preg_split('/\\s+-\\s+/', $source, 2);
        if ($parts === false || \count($parts) !== 2) {
            return [$source, null];
        }

        $base = \mb_trim($parts[0]);
        $suffix = \mb_trim($parts[1]);
        if ($suffix === '' || !$this->looksLikeAction($suffix)) {
            return [$source, null];
        }

        return [$base, $suffix];
    }

    /**
     * @return list<string>
     */
    private function ingredientFragments(string $source, string $original): array
    {
        $clean = \mb_trim(\preg_replace('/^\\+\\s*/u', '', $source) ?? $source);
        $clean = \mb_trim(\preg_replace('/^(?:smash|add|top with)\\s+/iu', '', $clean) ?? $clean);
        $clean = \mb_trim(\preg_replace('/^cloud:\\s*/iu', '', $clean) ?? $clean);

        if ($clean === '' || $this->isActionOnly($source)) {
            return [];
        }

        $plusParts = \preg_split('/\\s*\\+\\s*/', $clean);
        if ($plusParts === false) {
            return [$clean];
        }

        $fragments = [];
        foreach ($plusParts as $plusPart) {
            $plusPart = \mb_trim($plusPart);
            if ($plusPart === '') {
                continue;
            }
            $commaParts = $this->splitCommaFragments($plusPart);
            foreach ($commaParts as $commaPart) {
                if ($commaPart !== '') {
                    $fragments[] = $commaPart;
                }
            }
        }

        if ($fragments === []) {
            return [$original];
        }

        return $fragments;
    }

    /**
     * @return list<string>
     */
    private function splitCommaFragments(string $source): array
    {
        if (\preg_match('/\\d,\\d/u', $source) === 1) {
            return [$source];
        }

        $parts = \preg_split('/\\s*,\\s*/', $source);
        if ($parts === false || \count($parts) === 1) {
            return [$source];
        }

        return \array_values(\array_filter(\array_map(static fn(string $part): string => \mb_trim($part), $parts), static fn(string $part): bool => $part !== ''));
    }

    /**
     * @return array{quantity_value: float|int|null, quantity_text: string|null, unit: string|null, name: string, icon_group: string, source_text: string}|null
     */
    private function parseIngredient(string $source): array|null
    {
        $original = \mb_trim($source);
        if ($original === '' || !$this->hasIngredientSignal($original)) {
            return null;
        }

        $normalized = \mb_trim(\preg_replace('/^(?:fill up with|ice up to|pour on|on)\\s+/iu', '', $original) ?? $original);
        $quantityValue = null;
        $quantityText = null;
        $unit = null;
        $name = $normalized;

        if (\preg_match('/^(?<quantity>\\d+(?:[.,]\\d+)?|half|a few)\\s*(?<unit>kg|g|ml|l|spoons?|sp|cups?|pieces?)?(?=\\s|$)\\s*(?:of\\s+)?(?<name>.+)$/iu', $normalized, $matches) === 1) {
            $rawQuantity = \mb_trim($matches['quantity']);
            $unitValue = \mb_trim($matches['unit']);
            $name = \mb_trim($matches['name']);
            if (\is_numeric(\str_replace(',', '.', $rawQuantity))) {
                $number = (float) \str_replace(',', '.', $rawQuantity);
                $quantityValue = $number === \floor($number) ? (int) $number : $number;
                $quantityText = \str_contains($rawQuantity, ',') ? $rawQuantity : null;
            } else {
                $quantityText = $rawQuantity;
            }
            $unit = $unitValue !== '' ? $unitValue : null;
        } elseif (\preg_match('/^(?<name>.+?)\\s+to\\s+(?<quantity>\\d+(?:[.,]\\d+)?)\\s*(?<unit>ml|l|g|kg)$/iu', $normalized, $matches) === 1) {
            $rawQuantity = \mb_trim($matches['quantity']);
            $number = (float) \str_replace(',', '.', $rawQuantity);
            $quantityValue = $number === \floor($number) ? (int) $number : $number;
            $quantityText = \str_contains($rawQuantity, ',') ? $rawQuantity : null;
            $unit = \mb_trim($matches['unit']);
            $name = \mb_trim($matches['name']);
        }

        $name = \mb_trim(\preg_replace('/\\s+\\([^)]*\\)$/u', '', $name) ?? $name);
        if ($name === '') {
            return null;
        }

        return [
            'quantity_value' => $quantityValue,
            'quantity_text' => $quantityText,
            'unit' => $unit,
            'name' => $name,
            'icon_group' => $this->iconGroup($name),
            'source_text' => $original,
        ];
    }

    /**
     * Determine whether text contains enough evidence to be shown as an ingredient.
     */
    private function hasIngredientSignal(string $source): bool
    {
        $value = Str::lower($source);

        return \preg_match('/\\d/iu', $value) === 1 || $this->hasNamedIngredientSignal($value);
    }

    /**
     * Determine whether text contains a known ingredient name, excluding a
     * bare numeric duration or volume used by an action such as `set a timer`.
     */
    private function hasNamedIngredientSignal(string $source): bool
    {
        return \preg_match('/\\b(?:milk|water|tea|matcha|sugar|syrup|puree|fruit|mango|strawber\\w*|lychee|lemon|pineapple|peach|orange|ice|cream|smetana|cheese|salko|powder|taro|tapioca|oreo|lotus|pistachio|coconut|tea|pf|df|spoon|sp|cloud|topping|rim|flake|garnish)\\b/iu', $source) === 1;
    }

    /**
     * Keep clearly procedural source lines out of the ingredient list.
     */
    private function isActionOnly(string $source): bool
    {
        $value = \mb_trim(Str::lower($source));
        $prefixes = [
            'set a timer', 'bring to a boil', 'ice up', 'fill up', 'let cool',
            'stir', 'mix', 'whip', 'whisk', 'boil', 'simmer', 'steep', 'shake',
            'pour', 'smash', 'cook', 'cover', 'wash', 'serve', 'reduce',
        ];
        $prefixPattern = '/^(?:' . \implode('|', $prefixes) . ')\\b/iu';
        if (\preg_match($prefixPattern, $value) !== 1) {
            return false;
        }

        $remaining = \mb_trim(\preg_replace($prefixPattern, '', $value) ?? $value);
        if (Str::startsWith($value, 'wash ') && Str::startsWith($remaining, 'in ')) {
            return true;
        }

        return !$this->hasNamedIngredientSignal($remaining);
    }

    /**
     * Determine whether text includes a curated action verb.
     */
    private function looksLikeAction(string $source): bool
    {
        return \preg_match('/\\b(?:stir|mix|whip|whisk|boil|simmer|steep|shake|pour|smash|cook|cover|timer|fill up|ice up|top|around the rim|on top|wash|let cool|bring to a boil|serve|reduce)\\b/iu', $source) === 1;
    }

    /**
     * Resolve a stable action key for the procedure icon.
     */
    private function actionKey(string $source, string|null $suffix, bool $hasIngredients): string
    {
        $value = Str::lower($suffix ?? $source);
        $patterns = [
            'shake' => 'shake', 'whisk' => 'whisk', 'whip' => 'whip', 'stir' => 'stir', 'mix' => 'mix',
            'boil' => 'boil', 'simmer' => 'boil', 'steep' => 'steep', 'pour' => 'pour', 'smash' => 'smash',
            'cook' => 'cook', 'cover' => 'cover', 'timer' => 'timer', 'let cool' => 'cool', 'wash' => 'wash',
            'top' => 'garnish', 'around the rim' => 'garnish', 'on top' => 'garnish', 'serve' => 'serve',
            'ice' => 'ice', 'fill up' => 'ice', 'reduce' => 'boil', 'bring to a boil' => 'boil',
        ];
        foreach ($patterns as $needle => $action) {
            if (Str::contains($value, $needle)) {
                return $action;
            }
        }

        return $hasIngredients ? 'add' : 'other';
    }

    /**
     * Build a concise step label while retaining the exact source alongside it.
     */
    private function stepText(string $original, string $ingredientText, string|null $suffix, string $actionKey, bool $hasIngredients): string
    {
        if ($suffix !== null) {
            return $suffix;
        }
        if (!$hasIngredients || $actionKey !== 'add') {
            return $original;
        }

        return 'Add ' . \mb_trim($ingredientText);
    }

    /**
     * Infer the ingredient icon group from its normalized name.
     */
    private function iconGroup(string $name): string
    {
        $value = Str::lower($name);
        $groups = [
            'ice' => ['ice'],
            'milk_foam' => ['smetana', 'cream', 'foam', 'cloud', 'cheese', 'salko'],
            'tea_matcha' => ['tea', 'matcha', 'hojicha', 'jasmine', 'ceylon', 'oolong', 'butterfly', 'earl grey'],
            'fruit' => ['mango', 'strawberr', 'lychee', 'lemon', 'pineapple', 'peach', 'orange', 'passion', 'dragon', 'fruit', 'puree', 'pistachio'],
            'syrup_sweetener' => ['sugar', 'syrup', 'brown sugar', 'bs syrup'],
            'powder' => ['powder', 'taro', 'tapioca', 'oreo', 'lotus', 'gingerbread', 'brulee'],
            'water_milk' => ['water', 'milk', 'coconut', 'oat', 'smetana'],
            'topping_garnish' => ['rim', 'flake', 'dried', 'top', 'garnish'],
        ];
        foreach ($groups as $group => $needles) {
            foreach ($needles as $needle) {
                if (Str::contains($value, $needle)) {
                    return $group;
                }
            }
        }

        return 'neutral';
    }
}
