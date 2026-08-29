<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\RecipeInstruction;
use App\Models\RecipeVariant;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class ReadRecipesTool extends AbstractReadResourceTool
{
    /**
     * Stable provider-facing tool name.
     */
    public function name(): string { return 'read_recipes'; }

    /**
     * Explain the recipe and category datasets available to the model.
     */
    public function description(): string { return 'Read recipe categories or recipes with archive state, ordering, notes, variants, and full structured instructions.'; }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array { $filters = ['dataset' => $schema->string()->enum(['recipes', 'categories'])->required(), 'search' => $schema->string(), 'category_id' => $schema->integer(), 'archived' => $schema->boolean()];

        return ['request' => $schema->anyOf([$schema->object(['operation' => $schema->string()->enum(['list'])->required(), ...$filters, 'limit' => $schema->integer()->min(1)->max(50), 'cursor' => $schema->string()])->withoutAdditionalProperties(), $schema->object(['operation' => $schema->string()->enum(['detail'])->required(), 'dataset' => $schema->string()->enum(['recipes', 'categories'])->required(), 'id' => $schema->integer()->required()])->withoutAdditionalProperties(), $schema->object(['operation' => $schema->string()->enum(['summary'])->required(), ...$filters])->withoutAdditionalProperties()])->required()]; }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    protected function execute(array $request): array { $operation = Typer::parseNullableString($request['operation'] ?? null) ?? 'list';
        $dataset = $this->dataset($request);
        if ($dataset === 'categories') { return $this->categories($request, $operation); } if ($dataset !== 'recipes') { throw new InvalidArgumentException('Unknown recipe dataset.'); } $query = Recipe::query()->with(['category', 'variants.instructions']);
        Recipe::scopeForUser($query, $this->actor->resolveScopeUser());
        $search = Typer::parseNullableString($request['search'] ?? null);
        if ($search !== null && \mb_trim($search) !== '') { Recipe::scopeSearch($query, \mb_trim($search)); } $categoryId = Typer::parseNullableInt($request['category_id'] ?? null);
        if ($categoryId !== null) { $query->where('recipe_category_id', $categoryId); } if (\array_key_exists('archived', $request)) { (bool) $request['archived'] ? $query->whereNotNull('archived_at') : $query->whereNull('archived_at'); } if ($operation === 'detail') { $id = Typer::parseNullableInt($request['id'] ?? null);
            if ($id === null) { throw new InvalidArgumentException('A recipe identifier is required.'); }

            return $this->detailResult($request, 'recipes', $this->recipeRecord($query->findOrFail($id), true)); } if ($operation === 'summary') { $recipes = $query->get();

                return $this->summaryResult($request, 'recipes', ['recipe_count' => $recipes->count(), 'archived_count' => $recipes->filter(static fn(Recipe $recipe): bool => $recipe->isArchived())->count(), 'variant_count' => $recipes->sum(static fn(Recipe $recipe): int => $recipe->getVariants()->count())], $recipes->isEmpty() ? 'NO_MATCHING_DATA' : null); } if ($operation !== 'list') { throw new InvalidArgumentException('Unknown recipe read operation.'); }

        return $this->paginateById($query, $request, 'recipes', $request, fn(Recipe $recipe): array => $this->recipeRecord($recipe, false)); }

    /**
     * Resource identifier used by cursors, envelopes, and audits.
     */
    protected function resource(): string { return 'recipes'; }

    /**
     * @param array<string, mixed> $request
     */
    protected function dataset(array $request): string { return Typer::parseNullableString($request['dataset'] ?? null) ?? 'recipes'; }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private function categories(array $request, string $operation): array { $query = RecipeCategory::query()->where('user_id', $this->actor->resolveScopeUser()->getKey());
        $search = Typer::parseNullableString($request['search'] ?? null);
        if ($search !== null && \mb_trim($search) !== '') { RecipeCategory::scopeSearch($query, \mb_trim($search)); } $map = static fn(RecipeCategory $category): array => ['id' => $category->getKey(), 'name' => $category->getName(), 'position' => $category->getPosition(), 'url' => Resolver::resolveUrlGenerator()->route('recipes.categories.index')];
        if ($operation === 'detail') { $id = Typer::parseNullableInt($request['id'] ?? null);
            if ($id === null) { throw new InvalidArgumentException('A recipe category identifier is required.'); }

            return $this->detailResult($request, 'categories', $map($query->findOrFail($id))); } if ($operation === 'summary') { $count = $query->count();

                return $this->summaryResult($request, 'categories', ['category_count' => $count], $count === 0 ? 'NO_MATCHING_DATA' : null); } if ($operation !== 'list') { throw new InvalidArgumentException('Unknown recipe category operation.'); }

        return $this->paginateById($query, $request, 'categories', $request, $map); }

    /**
     * @return array<string, mixed>
     */
    private function recipeRecord(Recipe $recipe, bool $includeInstructions): array { $record = ['id' => $recipe->getKey(), 'category_id' => $recipe->getCategoryId(), 'category_name' => $recipe->getCategory()->getName(), 'name' => $recipe->getName(), 'note' => $recipe->getNote(), 'position' => $recipe->getPosition(), 'archived' => $recipe->isArchived(), 'variants' => $recipe->getVariants()->map(static fn(RecipeVariant $variant): array => ['id' => $variant->getKey(), 'name' => $variant->getName(), 'position' => $variant->getPosition(), ...($includeInstructions ? ['instructions' => $variant->getInstructions()->map(static fn(RecipeInstruction $instruction): array => ['id' => $instruction->getKey(), 'type' => $instruction->getType(), 'text' => $instruction->getText(), 'action_key' => $instruction->getActionKey(), 'quantity_value' => $instruction->getQuantityValue(), 'quantity_text' => $instruction->getQuantityText(), 'unit' => $instruction->getUnit(), 'ingredient_name' => $instruction->getIngredientName(), 'target' => $instruction->getTarget(), 'icon_group' => $instruction->getIconGroup()])->values()->all()] : [])])->values()->all(), 'url' => Resolver::resolveUrlGenerator()->route('recipes.show', $recipe->getKey())];

        return $record; }
}
