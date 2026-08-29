<?php

declare(strict_types=1);

namespace App\Ai\Operations\Recipes;

use App\Ai\Operations\AssistantOperationExecutor;
use App\Http\Validation\RecipeValidity;
use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\RecipeTestAttempt;
use App\Models\RecipeTestSession;
use App\Models\User;
use App\Models\Worker;
use App\Services\RecipeCatalogService;
use App\Services\RecipeTestService;
use App\Services\RecipeTestSessionService;
use InvalidArgumentException;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class RecipeOperationExecutor implements AssistantOperationExecutor
{
    /**
     * Create the service-backed recipe executor.
     */
    public function __construct(
        private readonly RecipeCatalogService $catalog,
        private readonly RecipeTestService $tests,
        private readonly RecipeTestSessionService $sessions,
    ) {}

    /**
     * Validate a proposal and resolve its exact company target.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    public function preview(string $identifier, User $actor, array $arguments): array
    {
        $this->assertAdmin($actor);
        $context = $this->json($arguments, 'context_json');
        $values = $this->json($arguments, 'values_json');
        $targetId = Typer::parseNullableInt($arguments['target_id'] ?? null);
        $this->validate($identifier, $actor, $context, $values);
        $targetType = $this->resolveTarget($identifier, $actor, $context, $targetId);

        return [
            'operation' => $identifier,
            'store' => null,
            'target' => $targetId === null ? null : ['type' => $targetType, 'id' => (string) $targetId],
            'effects' => $this->effects($identifier),
            'sanitized_arguments' => ['context' => $context, 'values' => $values],
            'safe_editable_fields' => ['values_json'],
        ];
    }

    /**
     * Execute an approved recipe action through the human-facing services.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    public function execute(string $identifier, User $actor, array $arguments): array
    {
        $this->assertAdmin($actor);
        $context = $this->json($arguments, 'context_json');
        $values = $this->json($arguments, 'values_json');
        $targetId = Typer::parseNullableInt($arguments['target_id'] ?? null);
        $this->validate($identifier, $actor, $context, $values);
        $this->resolveTarget($identifier, $actor, $context, $targetId);
        $recordId = $this->run($identifier, $actor, $context, $values, $targetId);

        return [
            'operation' => $identifier,
            'status' => 'succeeded',
            'record' => [
                'type' => $this->resultType($identifier),
                'id' => $recordId,
                'store_id' => null,
                'url' => $this->url($identifier, $recordId),
            ],
        ];
    }

    /**
     * Execute one fixed recipe operation.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $values
     */
    private function run(string $identifier, User $actor, array $context, array $values, int|null $targetId): int
    {
        if ($identifier === 'create_recipe_category') {
            return $this->catalog->createCategory($actor, \mb_trim(Typer::assertString($values['name'] ?? null)))->getKey();
        }

        if (\in_array($identifier, ['update_recipe_category', 'delete_recipe_category', 'move_recipe_category'], true)) {
            $category = $this->category($actor, Typer::assertInt($targetId));

            if ($identifier === 'update_recipe_category') {
                return $this->catalog->updateCategory($actor, $category, \mb_trim(Typer::assertString($values['name'] ?? null)))->getKey();
            }

            if ($identifier === 'delete_recipe_category') {
                return $this->deleteCategory($actor, $category);
            }

            return $this->moveCategory($actor, $category, Typer::assertString($values['direction'] ?? null));
        }

        if (\in_array($identifier, ['create_recipe', 'update_recipe'], true)) {
            $category = $this->category($actor, Typer::parseInt($context['category_id'] ?? null));
            $recipe = $identifier === 'update_recipe' ? $this->recipe($actor, Typer::assertInt($targetId)) : null;

            return $this->catalog->save(
                $actor,
                $category,
                $recipe,
                \mb_trim(Typer::assertString($values['name'] ?? null)),
                Typer::parseNullableString($values['note'] ?? null),
                $this->variants($values),
            )->getKey();
        }

        if (\in_array($identifier, ['archive_recipe', 'move_recipe'], true)) {
            $recipe = $this->recipe($actor, Typer::assertInt($targetId));

            if ($identifier === 'archive_recipe') {
                $this->catalog->setArchived($actor, $recipe, Typer::parseBool($values['archived'] ?? null));
            } else {
                $this->catalog->moveRecipe($actor, $recipe, Typer::assertString($values['direction'] ?? null));
            }

            return $recipe->getKey();
        }

        $delegatedActor = $this->delegatedActor($actor, Typer::parseInt($context['actor_user_id'] ?? null));

        if ($identifier === 'start_recipe_test_session') {
            return $this->sessions->start(
                $delegatedActor,
                $this->worker($actor, Typer::parseInt($context['worker_id'] ?? null)),
            )->getKey();
        }

        if ($identifier === 'submit_recipe_test') {
            return $this->tests->submit(
                $delegatedActor,
                $this->attempt($actor, $delegatedActor, Typer::assertInt($targetId)),
                \array_values(Typer::assertStringArray(Typer::assertArray($values['tokens'] ?? null))),
            )->getKey();
        }

        if ($identifier === 'submit_recipe_test_session') {
            return $this->sessions->submit(
                $delegatedActor,
                $this->session($actor, $delegatedActor, Typer::assertInt($targetId)),
                $this->answers($values),
            )->getKey();
        }

        throw new InvalidArgumentException('Unknown recipe operation.');
    }

    /**
     * Validate recipe values with the same validity rules as human forms.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $values
     */
    private function validate(string $identifier, User $actor, array $context, array $values): void
    {
        $validity = RecipeValidity::inject($actor->getKey());
        $payload = [...$context, ...$values];
        $rules = match ($identifier) {
            'create_recipe_category', 'update_recipe_category' => ['name' => $validity->categoryName()->required()->toArray()],
            'delete_recipe_category' => [],
            'move_recipe_category', 'move_recipe' => ['direction' => $validity->direction()->required()->toArray()],
            'create_recipe', 'update_recipe' => [
                'category_id' => $validity->categoryId()->required()->toArray(),
                'name' => $validity->name()->required()->toArray(),
                'note' => $validity->note()->nullable()->toArray(),
                'variants' => $validity->variants()->required()->toArray(),
                'variants.*.name' => $validity->variantName()->nullable()->toArray(),
                'variants.*.instructions' => $validity->instructions()->required()->toArray(),
                'variants.*.instructions.*.type' => $validity->instructionType()->required()->toArray(),
                'variants.*.instructions.*.text' => $validity->stepText()->required()->toArray(),
                'variants.*.instructions.*.action_key' => $validity->actionKey()->required()->toArray(),
                'variants.*.instructions.*.quantity_value' => $validity->ingredientQuantity()->nullable()->toArray(),
                'variants.*.instructions.*.quantity_text' => $validity->ingredientQuantityText()->nullable()->toArray(),
                'variants.*.instructions.*.unit' => $validity->ingredientUnit()->nullable()->toArray(),
                'variants.*.instructions.*.ingredient_name' => $validity->ingredientName()->nullable()->toArray(),
                'variants.*.instructions.*.target' => $validity->instructionTarget()->nullable()->toArray(),
                'variants.*.instructions.*.icon_group' => $validity->ingredientIconGroup()->required()->toArray(),
            ],
            'archive_recipe' => ['archived' => $validity->archived()->required()->toArray()],
            'start_recipe_test_session' => [
                'actor_user_id' => $validity->baseValidity->id()->required()->toArray(),
                'worker_id' => $validity->workerId()->required()->toArray(),
            ],
            'submit_recipe_test' => [
                'actor_user_id' => $validity->baseValidity->id()->required()->toArray(),
                'tokens' => $validity->tokens()->required()->toArray(),
                'tokens.*' => $validity->token()->required()->toArray(),
            ],
            'submit_recipe_test_session' => [
                'actor_user_id' => $validity->baseValidity->id()->required()->toArray(),
                'answers' => $validity->sessionAnswers()->required()->toArray(),
                'answers.*.attempt_id' => $validity->attemptId()->required()->toArray(),
                'answers.*.tokens' => $validity->tokens()->required()->toArray(),
                'answers.*.tokens.*' => $validity->token()->required()->toArray(),
                'answers.*.amounts' => $validity->amountAnswers()->required()->toArray(),
                'answers.*.amounts.*' => $validity->amountAnswer()->required()->toArray(),
            ],
            default => throw new InvalidArgumentException('Unknown recipe operation.'),
        };

        Resolver::resolveValidatorFactory()->make($payload, $rules)->validate();
    }

    /**
     * Resolve and authorize an operation target.
     *
     * @param array<string, mixed> $context
     */
    private function resolveTarget(string $identifier, User $actor, array $context, int|null $targetId): string|null
    {
        return match ($identifier) {
            'update_recipe_category', 'delete_recipe_category', 'move_recipe_category' => $this->resolvedCategory($actor, Typer::assertInt($targetId)),
            'update_recipe', 'archive_recipe', 'move_recipe' => $this->resolvedRecipe($actor, Typer::assertInt($targetId)),
            'submit_recipe_test' => $this->resolvedAttempt($actor, $context, Typer::assertInt($targetId)),
            'submit_recipe_test_session' => $this->resolvedSession($actor, $context, Typer::assertInt($targetId)),
            'create_recipe_category', 'create_recipe', 'start_recipe_test_session' => null,
            default => throw new InvalidArgumentException('Unknown recipe operation.'),
        };
    }

    /**
     * Resolve an owned category target type.
     */
    private function resolvedCategory(User $actor, int $targetId): string
    {
        $this->category($actor, $targetId);

        return 'recipe_category';
    }

    /**
     * Resolve an owned recipe target type.
     */
    private function resolvedRecipe(User $actor, int $targetId): string
    {
        $this->recipe($actor, $targetId);

        return 'recipe';
    }

    /**
     * Resolve an owned test-attempt target type.
     *
     * @param array<string, mixed> $context
     */
    private function resolvedAttempt(User $actor, array $context, int $targetId): string
    {
        $delegatedActor = $this->delegatedActor($actor, Typer::parseInt($context['actor_user_id'] ?? null));
        $this->attempt($actor, $delegatedActor, $targetId);

        return 'recipe_test_attempt';
    }

    /**
     * Resolve an owned test-session target type.
     *
     * @param array<string, mixed> $context
     */
    private function resolvedSession(User $actor, array $context, int $targetId): string
    {
        $delegatedActor = $this->delegatedActor($actor, Typer::parseInt($context['actor_user_id'] ?? null));
        $this->session($actor, $delegatedActor, $targetId);

        return 'recipe_test_session';
    }

    /**
     * Resolve an owned recipe category.
     */
    private function category(User $actor, int $id): RecipeCategory
    {
        return Typer::assertInstance(RecipeCategory::query()->where('user_id', $actor->getKey())->whereKey($id)->firstOrFail(), RecipeCategory::class);
    }

    /**
     * Resolve an owned recipe.
     */
    private function recipe(User $actor, int $id): Recipe
    {
        return Typer::assertInstance(Recipe::query()->where('user_id', $actor->getKey())->whereKey($id)->firstOrFail(), Recipe::class);
    }

    /**
     * Resolve a worker owned by the main admin.
     */
    private function worker(User $actor, int $id): Worker
    {
        return Typer::assertInstance(Worker::query()->where('user_id', $actor->getKey())->whereKey($id)->firstOrFail(), Worker::class);
    }

    /**
     * Resolve a limited account explicitly delegated by the main admin.
     */
    private function delegatedActor(User $actor, int $id): User
    {
        $query = User::query();
        User::scopeLimited($query);

        return Typer::assertInstance($query->where('parent_user_id', $actor->getKey())->whereKey($id)->firstOrFail(), User::class);
    }

    /**
     * Resolve an attempt created by the delegated limited account.
     */
    private function attempt(User $actor, User $delegatedActor, int $id): RecipeTestAttempt
    {
        return Typer::assertInstance(RecipeTestAttempt::query()
            ->where('user_id', $actor->getKey())
            ->where('actor_user_id', $delegatedActor->getKey())
            ->whereKey($id)
            ->firstOrFail(), RecipeTestAttempt::class);
    }

    /**
     * Resolve a session created by the delegated limited account.
     */
    private function session(User $actor, User $delegatedActor, int $id): RecipeTestSession
    {
        return Typer::assertInstance(RecipeTestSession::query()
            ->where('user_id', $actor->getKey())
            ->where('actor_user_id', $delegatedActor->getKey())
            ->whereKey($id)
            ->firstOrFail(), RecipeTestSession::class);
    }

    /**
     * Return normalized variants accepted by the recipe catalog service.
     *
     * @param array<string, mixed> $values
     *
     * @return list<array{name: string|null, instructions: list<array<string, mixed>>}>
     */
    private function variants(array $values): array
    {
        $variants = [];

        foreach (Typer::assertArray($values['variants'] ?? null) as $variantValue) {
            $variant = Typer::assertStringKeyArray(Typer::assertArray($variantValue));
            $instructions = [];

            foreach (Typer::assertArray($variant['instructions'] ?? null) as $instructionValue) {
                $instructions[] = Typer::assertStringKeyArray(Typer::assertArray($instructionValue));
            }

            $name = Typer::parseNullableString($variant['name'] ?? null);
            $variants[] = [
                'name' => $name === null || \mb_trim($name) === '' ? null : \mb_trim($name),
                'instructions' => $instructions,
            ];
        }

        return $variants;
    }

    /**
     * Return normalized recipe-session answers.
     *
     * @param array<string, mixed> $values
     *
     * @return list<array{attempt_id: int, tokens: list<string>, amounts: array<string, string>}>
     */
    private function answers(array $values): array
    {
        $answers = [];

        foreach (Typer::assertArray($values['answers'] ?? null) as $answerValue) {
            $answer = Typer::assertStringKeyArray(Typer::assertArray($answerValue));
            $amounts = [];

            foreach (Typer::assertArray($answer['amounts'] ?? null) as $token => $amount) {
                $amounts[Typer::assertString($token)] = Typer::assertString($amount);
            }

            $answers[] = [
                'attempt_id' => Typer::parseInt($answer['attempt_id'] ?? null),
                'tokens' => \array_values(Typer::assertStringArray(Typer::assertArray($answer['tokens'] ?? null))),
                'amounts' => $amounts,
            ];
        }

        return $answers;
    }

    /**
     * Delete an empty category or fail like the human UI.
     */
    private function deleteCategory(User $actor, RecipeCategory $category): int
    {
        if (!$this->catalog->deleteCategory($actor, $category)) {
            throw new RuntimeException('A category containing recipes cannot be deleted.');
        }

        return $category->getKey();
    }

    /**
     * Move a category and return its identifier.
     */
    private function moveCategory(User $actor, RecipeCategory $category, string $direction): int
    {
        $this->catalog->moveCategory($actor, $category, $direction);

        return $category->getKey();
    }

    /**
     * Ensure the assistant actor is the owning main admin.
     */
    private function assertAdmin(User $actor): void
    {
        if (!$actor->isAdmin()) {
            \abort(403);
        }
    }

    /**
     * Describe the durable business effect shown before approval.
     */
    private function effects(string $identifier): string
    {
        return match ($identifier) {
            'create_recipe_category' => 'Creates an ordered recipe category.',
            'update_recipe_category' => 'Renames the selected recipe category.',
            'delete_recipe_category' => 'Deletes the selected category only when it contains no recipes.',
            'move_recipe_category' => 'Reorders the category by one position.',
            'create_recipe' => 'Creates the recipe, variants, and ordered instructions transactionally.',
            'update_recipe' => 'Replaces the selected recipe structure transactionally.',
            'archive_recipe' => 'Archives or restores the selected recipe without deleting test history.',
            'move_recipe' => 'Reorders the recipe by one position within its category.',
            'start_recipe_test_session' => 'Starts the same three-recipe test session as the delegated limited account.',
            'submit_recipe_test' => 'Scores and records one delegated recipe test attempt.',
            'submit_recipe_test_session' => 'Scores the full delegated test session and emits its normal operational activity.',
            default => throw new InvalidArgumentException('Unknown recipe operation.'),
        };
    }

    /**
     * Resolve the result record type.
     */
    private function resultType(string $identifier): string
    {
        return match (true) {
            \str_contains($identifier, 'category') => 'recipe_category',
            $identifier === 'start_recipe_test_session', $identifier === 'submit_recipe_test_session' => 'recipe_test_session',
            $identifier === 'submit_recipe_test' => 'recipe_test_attempt',
            default => 'recipe',
        };
    }

    /**
     * Build the normal application link for the operation result.
     */
    private function url(string $identifier, int|null $id): string
    {
        if ($identifier === 'start_recipe_test_session' || $identifier === 'submit_recipe_test_session') {
            return Resolver::resolveUrlGenerator()->route('recipe-test-sessions.show', Typer::assertInt($id));
        }

        if ($identifier === 'submit_recipe_test') {
            return Resolver::resolveUrlGenerator()->route('recipe-test-results.show', Typer::assertInt($id));
        }

        if (\str_contains($identifier, 'category')) {
            return Resolver::resolveUrlGenerator()->route('recipe-categories.index');
        }

        if (\in_array($identifier, ['create_recipe', 'update_recipe'], true)) {
            return Resolver::resolveUrlGenerator()->route('recipes.show', Typer::assertInt($id));
        }

        return Resolver::resolveUrlGenerator()->route('recipes.index');
    }

    /**
     * Decode a bounded JSON object from the mutation envelope.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function json(array $arguments, string $key): array
    {
        $json = Typer::parseNullableString($arguments[$key] ?? null) ?? '{}';

        if (\mb_strlen($json) > 50_000) {
            throw new InvalidArgumentException('Assistant operation arguments are too large.');
        }

        return Typer::assertStringKeyArray(Typer::assertArray(\json_decode($json, true, 32, \JSON_THROW_ON_ERROR)));
    }
}
