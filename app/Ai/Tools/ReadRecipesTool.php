<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Domain\Recipes\RecipeCatalogRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Typer;

final class ReadRecipesTool extends AbstractReadResourceTool
{
    private const array STOP_WORDS = ['a', 'an', 'ako', 'and', 'by', 'co', 'dela', 'delame', 'do', 'for', 'how', 'i', 'is', 'jak', 'je', 'make', 'mi', 'my', 'nas', 'nase', 'naseho', 'nasi', 'of', 'our', 'podle', 'postup', 'prepare', 'pripravit', 'priprava', 'prosim', 'recept', 'recipe', 'receptu', 'robi', 'robime', 'sa', 'se', 'show', 'the', 'to', 'udelat', 'ukaz', 'what', 'z', 'ze'];

    /**
     * Retain the stable provider-facing recipe read name.
     */
    public function name(): string { return 'read_recipes'; }

    /**
     * Describe full authored recipe lookup and its company scope.
     */
    public function description(): string
    {
        return 'Read the authored company recipe library. Use lookup for named recipes or preparation questions to return complete variants, measured ingredients, ordered methods, and related preparations. Recipe identities are category/recipe slugs. Categories alone cannot establish whether a recipe exists. Recipes are company-wide, not store-scoped.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $filters = ['search' => $schema->string(), 'category' => $schema->string()];

        return ['request' => $schema->anyOf([
            $schema->object(['operation' => $schema->string()->enum(['lookup'])->required(), 'dataset' => $schema->string()->enum(['recipes'])->required(), 'query' => $schema->string()->required(), 'category' => $schema->string(), 'limit' => $schema->integer()->min(1)->max(50), 'cursor' => $schema->string()])->withoutAdditionalProperties(),
            $schema->object(['operation' => $schema->string()->enum(['list'])->required(), 'dataset' => $schema->string()->enum(['recipes', 'categories']), ...$filters, 'limit' => $schema->integer()->min(1)->max(50), 'cursor' => $schema->string()])->withoutAdditionalProperties(),
            $schema->object(['operation' => $schema->string()->enum(['detail'])->required(), 'dataset' => $schema->string()->enum(['recipes', 'categories']), 'id' => $schema->string()->description('Category slug, or category/recipe slug for a recipe.')->required()])->withoutAdditionalProperties(),
            $schema->object(['operation' => $schema->string()->enum(['summary'])->required(), 'dataset' => $schema->string()->enum(['recipes', 'categories']), ...$filters])->withoutAdditionalProperties(),
        ])->required()];
    }

    /** @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    protected function execute(array $request): array
    {
        $catalog = new RecipeCatalogRepository();
        $operation = Typer::parseNullableString($request['operation'] ?? null) ?? 'list';
        $dataset = Typer::parseNullableString($request['dataset'] ?? null) ?? 'recipes';
        if (!\in_array($dataset, ['recipes', 'categories'], true)) {
            throw new InvalidArgumentException('Unknown recipe dataset.');
        }
        if (!\in_array($operation, ['lookup', 'list', 'detail', 'summary'], true) || ($operation === 'lookup' && $dataset !== 'recipes')) {
            throw new InvalidArgumentException('Unknown recipe operation.');
        }
        $records = $dataset === 'categories'
            ? \array_map(static fn(array $category): array => ['id' => $category['key'], ...$category, 'url' => '/recipes?category=' . $category['key']], $catalog->categories())
            : \array_map(static fn(array $recipe): array => ['id' => $recipe['key'], ...$recipe], $catalog->recipes());
        if ($operation === 'detail') {
            $id = Typer::parseNullableString($request['id'] ?? null) ?? throw new InvalidArgumentException('A recipe slug is required.');
            $record = \array_find($records, static fn(array $record): bool => $record['id'] === $id) ?? throw new InvalidArgumentException('Unknown recipe slug.');

            return $this->context($this->detailResult($request, $dataset, $record), $dataset);
        }
        $search = Typer::parseNullableString($request[$operation === 'lookup' ? 'query' : 'search'] ?? null) ?? '';
        $tokens = $this->tokens($search, $operation === 'lookup');
        if ($operation === 'lookup' && $tokens === []) {
            throw new InvalidArgumentException('Include a recipe name or distinctive recipe words.');
        }
        $category = Typer::parseNullableString($request['category'] ?? null);
        $records = \array_values(\array_filter($records, static function (array $record) use ($tokens, $category): bool {
            if ($category !== null && isset($record['category']) && $record['category']['key'] !== $category) {
                return false;
            }
            $haystack = Str::lower(Str::ascii($record['name'] . ' ' . \implode(' ', $record['aliases'] ?? []) . ' ' . ($record['category']['name'] ?? '')));
            foreach ($tokens as $token) {
                if (!\str_contains($haystack, $token)) {
                    return false;
                }
            }

            return true;
        }));
        if ($operation === 'summary') {
            return $this->context($this->summaryResult($request, $dataset, $dataset === 'categories'
                ? ['category_count' => \count($records)]
                : ['recipe_count' => \count($records), 'variant_count' => \array_sum(\array_map(static fn(array $record): int => \count($record['variants'] ?? []), $records))]), $dataset);
        }
        if ($operation === 'list' && $dataset === 'recipes') {
            $records = \array_map(static function (array $record): array {
                $record['variant_count'] = \count($record['variants'] ?? []);
                unset($record['variants']);

                return $record;
            }, $records);
        }
        $hash = \hash('sha256', \json_encode($catalog->recipes(), \JSON_THROW_ON_ERROR));
        $state = $this->cursorState($request, $dataset, $request);
        if (isset($state['after']['hash']) && $state['after']['hash'] !== $hash) {
            return $this->dataChangedResult($request, $dataset, $state['as_of']);
        }
        $after = Typer::parseNullableString($state['after']['id'] ?? null);
        if ($after !== null) {
            $index = \array_search($after, \array_column($records, 'id'), true);
            if ($index === false) {
                throw new InvalidArgumentException('The recipe cursor is no longer valid.');
            }
            $records = \array_slice($records, $index + 1);
        }
        $limit = $this->limit($request);
        $page = \array_slice($records, 0, $limit);
        $result = $this->listResult($request, $dataset, $page, $request, $limit < \count($records), ['id' => $page === [] ? null : $page[\array_key_last($page)]['id'], 'hash' => $hash]);
        if ($operation === 'lookup') {
            $result['matched_terms'] = $tokens;
        }

        return $this->context($result, $dataset);
    }

    /**
     * Use the existing resource identity for envelopes and cursor binding.
     */
    protected function resource(): string { return 'recipes'; }

    /**
     * @return list<string>
     */
    private function tokens(string $query, bool $natural): array
    {
        $tokens = \preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($query)), flags: \PREG_SPLIT_NO_EMPTY);

        return \array_values(\array_filter(
            $tokens === false ? [] : $tokens,
            static fn(string $token): bool => !$natural || (\mb_strlen($token) >= 2 && !\in_array($token, self::STOP_WORDS, true)),
        ));
    }

    /** @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function context(array $result, string $dataset): array
    {
        $result['scope'] = ['type' => 'company', 'store_scoped' => false];
        if ($dataset === 'categories') {
            $result['capability'] = ['can_determine_recipe_existence' => false, 'recipe_lookup_operation' => 'lookup', 'recipe_lookup_dataset' => 'recipes'];
        }

        return $result;
    }
}
