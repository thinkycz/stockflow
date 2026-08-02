<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeInstruction;
use App\Models\RecipeStep;
use App\Models\RecipeVariant;
use App\Models\User;
use App\Support\RecipeDefaultCatalog;
use App\Support\RecipeTextParser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Thinkycz\LaravelCore\Support\Typer;

class RecipeInstructionService
{
    /**
     * Build the canonical sequence once without deleting legacy recipe content.
     */
    public function initialize(User $owner): void
    {
        DB::transaction(function () use ($owner): void {
            $locked = Typer::assertInstance(User::query()->whereKey($owner->getKey())->lockForUpdate()->firstOrFail(), User::class);
            if ($locked->getRecipeInstructionsInitializedAt() !== null) {
                return;
            }

            $recipes = Recipe::query()->where('user_id', $locked->getKey())
                ->with(['category', 'variants.ingredients', 'variants.steps', 'variants.instructions'])
                ->orderBy('recipe_category_id')->orderBy('position')->get();
            foreach ($recipes as $value) {
                $recipe = Typer::assertInstance($value, Recipe::class);
                foreach ($recipe->getVariants() as $variantValue) {
                    $variant = Typer::assertInstance($variantValue, RecipeVariant::class);
                    if ($variant->getInstructions()->isNotEmpty()) {
                        continue;
                    }
                    foreach ($this->sequence($recipe, $variant) as $position => $row) {
                        RecipeInstruction::query()->create([
                            'recipe_variant_id' => $variant->getKey(),
                            'position' => $position + 1,
                            ...$row,
                        ]);
                    }
                }
            }

            $locked->setAttribute('recipe_instructions_initialized_at', Carbon::now());
            $locked->save();
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sequence(Recipe $recipe, RecipeVariant $variant): array
    {
        if ($recipe->getName() === 'CLASSIC MATCHA LATTE') {
            return $this->classicSequence($variant);
        }

        $sourceLines = $this->sourceLines($recipe->getName(), $variant->getName());
        $ingredients = $variant->getIngredients()->values();
        $steps = $variant->getSteps()->values();
        $usedStepIds = [];
        $ingredientOffset = 0;
        $rows = [];
        foreach ($sourceLines as $sourceLine) {
            $parsed = (new RecipeTextParser())->parse($sourceLine);
            $lineIngredients = $ingredients->slice($ingredientOffset, \count($parsed['ingredients']))->values();
            $ingredientOffset += $lineIngredients->count();
            $lineSteps = $steps->filter(static fn(RecipeStep $step): bool => $sourceLine === $step->getSourceText())->values();
            foreach ($lineSteps as $step) {
                $usedStepIds[] = $step->getKey();
            }
            $rows = [...$rows, ...$this->lineRows($recipe, $sourceLine, $lineIngredients, $lineSteps)];
        }

        foreach ($ingredients->slice($ingredientOffset) as $value) {
            $ingredient = Typer::assertInstance($value, RecipeIngredient::class);
            $rows[] = $this->ingredientRow($ingredient, $this->target($recipe, $ingredient->getSourceText(), 'add'));
        }
        foreach ($steps as $value) {
            $step = Typer::assertInstance($value, RecipeStep::class);
            if (!\in_array($step->getKey(), $usedStepIds, true)) {
                $rows[] = $this->actionRow($this->actionText($step->getText(), $step->getActionKey()), $step->getActionKey(), $step->getSourceText());
            }
        }

        return $rows;
    }

    /**
     * @param Collection<array-key, RecipeIngredient> $ingredients
     * @param Collection<array-key, RecipeStep> $steps
     *
     * @return list<array<string, mixed>>
     */
    private function lineRows(Recipe $recipe, string $source, Collection $ingredients, Collection $steps): array
    {
        $actionKey = $steps->first()?->getActionKey() ?? 'add';
        $target = $this->target($recipe, $source, $actionKey);
        $rows = [];
        foreach ($ingredients as $value) {
            $rows[] = $this->ingredientRow(Typer::assertInstance($value, RecipeIngredient::class), $target);
        }

        foreach ($steps as $value) {
            $step = Typer::assertInstance($value, RecipeStep::class);
            if ($step->getActionKey() === 'add' || ($step->getActionKey() === 'ice' && $ingredients->isNotEmpty())) {
                continue;
            }
            foreach ($this->splitActions($step) as $row) {
                $rows[] = $row;
            }
        }

        $lower = Str::lower($source);
        $matchaBowl = Str::contains($lower, 'water') && (Str::contains($lower, 'matcha') || Str::contains($lower, 'hojicha')) && !Str::contains($lower, ['whip', 'cloud']);
        if ($matchaBowl && !$this->hasAction($rows, 'whisk')) {
            $rows[] = $this->actionRow('Use Matcha Whisk', 'whisk', $source);
        }
        if ($matchaBowl && !$this->hasAction($rows, 'pour')) {
            $rows[] = $this->actionRow('Pour into cup', 'pour', $source);
        }
        if ((Str::contains($lower, 'cloud') || Str::contains($lower, 'whip')) && !$this->hasAction($rows, 'pour')) {
            $rows[] = $this->actionRow('Pour into cup', 'pour', $source);
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function classicSequence(RecipeVariant $variant): array
    {
        $ingredients = $variant->getIngredients()->values();
        if ($ingredients->count() < 5) {
            return [];
        }
        $milk = Typer::assertInstance($ingredients->get(0), RecipeIngredient::class);
        $sugar = Typer::assertInstance($ingredients->get(1), RecipeIngredient::class);
        $ice = Typer::assertInstance($ingredients->get(2), RecipeIngredient::class);
        $water = Typer::assertInstance($ingredients->get(3), RecipeIngredient::class);
        $matcha = Typer::assertInstance($ingredients->get(4), RecipeIngredient::class);

        return [
            $this->ingredientRow($milk, 'cup', 'ml'),
            $this->ingredientRow($sugar, 'cup'),
            $this->actionRow('Stir', 'stir', $milk->getSourceText()),
            $this->ingredientRow($ice, 'cup'),
            $this->ingredientRow($water, 'matcha bowl'),
            $this->ingredientRow($matcha, 'matcha bowl'),
            $this->actionRow('Use Matcha Whisk', 'whisk', $matcha->getSourceText()),
            $this->actionRow('Pour into cup', 'pour', $matcha->getSourceText()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ingredientRow(RecipeIngredient $ingredient, string|null $target, string|null $unitOverride = null): array
    {
        $quantity = $ingredient->getQuantityText();
        if ($quantity === null && $ingredient->getQuantityValue() !== null) {
            $quantity = (string) $ingredient->getQuantityValue();
        }
        $unit = $unitOverride ?? $ingredient->getUnit();
        $name = $ingredient->getName() === 'ice' ? 'Ice' : $ingredient->getName();
        $amount = \mb_trim(\implode(' ', \array_filter([$quantity, $unit], static fn(string|null $value): bool => $value !== null && $value !== '')));
        $text = 'Add ' . ($amount !== '' ? $amount . ' ' : '') . $name . ($target !== null ? ' into ' . $target : '');

        return [
            'type' => 'ingredient', 'text' => $text, 'action_key' => 'add',
            'quantity_value' => $ingredient->getQuantityValue(), 'quantity_text' => $ingredient->getQuantityText(),
            'unit' => $unit, 'ingredient_name' => $name, 'target' => $target,
            'icon_group' => $ingredient->getIconGroup(), 'source_text' => $ingredient->getSourceText(), 'is_inferred' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function actionRow(string $text, string $actionKey, string|null $source): array
    {
        return [
            'type' => 'action', 'text' => $text, 'action_key' => $actionKey,
            'quantity_value' => null, 'quantity_text' => null, 'unit' => null,
            'ingredient_name' => null, 'target' => null, 'icon_group' => 'neutral',
            'source_text' => $source, 'is_inferred' => true,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function splitActions(RecipeStep $step): array
    {
        $text = Str::lower($step->getText());
        if (Str::contains($text, 'whisk') && Str::contains($text, 'pour')) {
            return [$this->actionRow('Use Matcha Whisk', 'whisk', $step->getSourceText()), $this->actionRow('Pour into cup', 'pour', $step->getSourceText())];
        }
        if (Str::contains($text, 'smash') && Str::contains($text, 'pour')) {
            return [$this->actionRow('Smash', 'smash', $step->getSourceText()), $this->actionRow('Pour into cup', 'pour', $step->getSourceText())];
        }

        return [$this->actionRow($this->actionText($step->getText(), $step->getActionKey()), $step->getActionKey(), $step->getSourceText())];
    }

    /**
     * Normalize a legacy action into its production instruction wording.
     */
    private function actionText(string $text, string $actionKey): string
    {
        return match ($actionKey) {
            'whisk' => 'Use Matcha Whisk',
            'pour' => Str::contains(Str::lower($text), 'cup') ? Str::ucfirst($text) : 'Pour into cup',
            default => Str::ucfirst($text),
        };
    }

    /**
     * Infer the target vessel from the recipe category and source wording.
     */
    private function target(Recipe $recipe, string $source, string $actionKey): string|null
    {
        $category = Str::upper($recipe->getCategory()->getName());
        $lower = Str::lower($source);
        if ($category === 'PREPARATIONS') {
            if (Str::contains($lower, 'pot')) {
                return 'pot';
            }
            if (Str::contains($lower, 'machine')) {
                return 'machine';
            }

            return null;
        }
        if (Str::contains($lower, ['cloud', 'smetana']) || $actionKey === 'whip') {
            return 'mixing bowl';
        }
        if (Str::contains($lower, 'water') && Str::contains($lower, ['matcha', 'hojicha'])) {
            return 'matcha bowl';
        }
        if (Str::contains($category, ['FRESH FRUIT TEA', 'MILK TEA']) || Str::contains($lower, 'shake')) {
            return 'shaker';
        }

        return 'cup';
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function hasAction(array $rows, string $actionKey): bool
    {
        foreach ($rows as $row) {
            if (($row['action_key'] ?? null) === $actionKey) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function sourceLines(string $recipeName, string|null $variantName): array
    {
        foreach (RecipeDefaultCatalog::categories() as $category) {
            foreach ($category['recipes'] as $recipe) {
                if ($recipe['name'] !== $recipeName) {
                    continue;
                }
                foreach ($recipe['variants'] as $variant) {
                    if ($variant['name'] === $variantName) {
                        return $variant['steps'];
                    }
                }
            }
        }

        return [];
    }
}
