<?php

declare(strict_types=1);

use App\Ai\AssistantToolCatalog;
use App\Ai\Tools\ReadRecipesTool;
use Laravel\Ai\Tools\Request;
use Thinkycz\LaravelCore\Support\Typer;

\test('assistant recipe lookup returns the same full file recipe for natural language questions', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $result = \fileRecipeRead(new ReadRecipesTool($admin, 'file-recipe-lookup'), ['operation' => 'lookup', 'dataset' => 'recipes', 'query' => 'Jak připravit Classic Matcha?']);
    \expect($result['returned_count'])->toBe(2)
        ->and($result['scope'])->toBe(['type' => 'company', 'store_scoped' => false]);
    $records = Typer::assertArray($result['records']);
    $hot = Typer::assertArray(\array_find($records, static fn(mixed $row): bool => Typer::assertArray($row)['id'] === 'hot-drinks/classic-matcha'));
    $variants = Typer::assertArray($hot['variants']);
    $variant = Typer::assertArray($variants[0]);
    \expect(Typer::assertArray($variant['ingredients'])[0])->toMatchArray(['name' => 'milk', 'quantity_value' => 200, 'unit' => 'g'])
        ->and(\array_column(Typer::assertArray($variant['steps']), 'action_key'))->toContain('steam', 'whisk')
        ->and($hot['url'])->toBe('/recipes/hot-drinks/classic-matcha');
});

\test('file recipe lists paginate without gaps or database catalog records', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $tool = new ReadRecipesTool($admin, 'file-recipe-pagination');
    $first = \fileRecipeRead($tool, ['operation' => 'list', 'dataset' => 'recipes', 'limit' => 50]);
    $second = \fileRecipeRead($tool, ['operation' => 'list', 'dataset' => 'recipes', 'limit' => 50, 'cursor' => $first['next_cursor']]);
    $ids = [...\array_column(Typer::assertArray($first['records']), 'id'), ...\array_column(Typer::assertArray($second['records']), 'id')];
    \expect($first['has_more'])->toBeTrue()
        ->and($first['returned_count'])->toBe(50)
        ->and($second['returned_count'])->toBe(4)
        ->and($second['complete'])->toBeTrue()
        ->and(\array_unique($ids))->toHaveCount(54);
    $summary = \fileRecipeRead($tool, ['operation' => 'summary', 'dataset' => 'recipes']);
    \expect($summary['summary'])->toMatchArray(['recipe_count' => 54, 'variant_count' => 189]);
});

\test('assistant categories cannot establish recipe absence and retired write and staff test tools are absent', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $catalog = new AssistantToolCatalog();
    $categories = \fileRecipeRead(new ReadRecipesTool($admin, 'file-recipe-categories'), ['operation' => 'list', 'dataset' => 'categories']);
    \expect($categories['returned_count'])->toBe(9)
        ->and($categories['capability'])->toMatchArray(['can_determine_recipe_existence' => false]);
    foreach (['write_recipes', 'read_recipe_tests', 'write_recipe_tests'] as $name) {
        \expect($catalog->find($admin, 'removed-recipe-features', $name))->toBeNull();
    }
});

/** @param array<string, mixed> $request
 * @return array<string, mixed>
 */
function fileRecipeRead(ReadRecipesTool $tool, array $request): array
{
    return Typer::assertStringKeyArray(Typer::assertArray(\json_decode($tool->handle(new Request(['request' => $request], 'file-read')), true, flags: \JSON_THROW_ON_ERROR)));
}
