<?php

declare(strict_types=1);

use App\Domain\Recipes\RecipeCatalogService;
use App\Domain\Recipes\RecipeInstructionService;
use App\Domain\Recipes\RecipeTestService;
use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\RecipeIngredient;
use App\Models\RecipeInstruction;
use App\Models\RecipeStep;
use App\Models\RecipeTestAttempt;
use App\Models\RecipeVariant;
use App\Models\User;
use App\Models\Worker;
use Database\Factories\UserFactory;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Support\Typer;

\test('initializes the complete recipe catalog once without overwriting changes', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $service = new RecipeCatalogService();

    $service->initialize($admin);

    \expect(RecipeCategory::query()->where('user_id', $admin->getKey())->count())->toBe(8)
        ->and(Recipe::query()->where('user_id', $admin->getKey())->count())->toBe(49)
        ->and(RecipeVariant::query()->count())->toBe(184)
        ->and(Recipe::query()->where('name', 'Classic Matcha Latte')->exists())->toBeTrue()
        ->and(Recipe::query()->where('name', 'Creme Brulee')->exists())->toBeTrue();

    $classic = Typer::assertInstance(Recipe::query()->where('name', 'Classic Matcha Latte')->with(['variants.ingredients', 'variants.steps', 'variants.instructions'])->firstOrFail(), Recipe::class);
    $creamCheese = Typer::assertInstance(Recipe::query()->where('name', 'Cream Cheese')->with('variants.instructions')->firstOrFail(), Recipe::class);
    \expect($classic->getVariants()->map(static fn(RecipeVariant $variant): string|null => $variant->getName())->all())->toBe(['S — With ice', 'S — No ice', 'M — With ice', 'M — No ice'])
        ->and($classic->getVariants()->first()?->getSteps())->toBeEmpty()
        ->and($classic->getVariants()->first()?->getIngredients())->toBeEmpty()
        ->and($classic->getVariants()->first()?->getInstructions()->map(static fn(RecipeInstruction $instruction): string => $instruction->getText())->all())->toBe([
            'Add 100 ml milk to serving cup.',
            'Add 20 ml liquid sugar to serving cup.',
            'Stir until combined.',
            'Fill the serving cup with ice.',
            'Add 50 ml water at 70–80 °C to matcha bowl.',
            'Add 3.5 g matcha to matcha bowl.',
            'Whisk until smooth.',
            'Pour the matcha into the serving cup.',
        ])
        ->and($classic->getVariants()->first()?->getInstructions()->every(static fn(RecipeInstruction $instruction): bool => !$instruction->isInferred()))->toBeTrue()
        ->and($creamCheese->getNote())->toBe('Use within 5 days.')
        ->and($creamCheese->getVariants()->map(static fn(RecipeVariant $variant): string|null => $variant->getName())->all())->toBe(['Batch', '1 portion'])
        ->and($creamCheese->getVariants()->last()?->getInstructions()->map(static fn(RecipeInstruction $instruction): string => $instruction->getText())->all())->toBe([
            'Add 50 ml whipping cream to mixing bowl.',
            'Add 20 ml sweetened condensed milk (Salko) to mixing bowl.',
            'Add 20 ml milk to mixing bowl.',
            'Add 10 g cream cheese to mixing bowl.',
            'Whip until thick.',
        ]);

    Recipe::query()->where('name', 'Classic Matcha Latte')->update(['name' => 'Edited recipe']);
    $service->initialize($admin);

    \expect(Recipe::query()->where('user_id', $admin->getKey())->count())->toBe(49)
        ->and(Recipe::query()->where('name', 'Edited recipe')->exists())->toBeTrue();
});

\test('scores a recipe attempt from its immutable snapshot', function (): void {
    Carbon::setTestNow('2026-08-02 12:00:00');
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $actor = Typer::assertInstance(UserFactory::new()->createOne([
        'parent_user_id' => $admin->getKey(),
        'assigned_store_id' => null,
    ]), User::class);
    $worker = Typer::assertInstance(Worker::factory()->createOne(['user_id' => $admin->getKey()]), Worker::class);
    $category = Typer::assertInstance(RecipeCategory::query()->create([
        'user_id' => $admin->getKey(), 'name' => 'Test', 'position' => 1,
    ]), RecipeCategory::class);
    $recipe = Typer::assertInstance(Recipe::query()->create([
        'user_id' => $admin->getKey(), 'recipe_category_id' => $category->getKey(),
        'name' => 'Latte', 'note' => null, 'position' => 1, 'archived_at' => null,
    ]), Recipe::class);
    $variant = Typer::assertInstance(RecipeVariant::query()->create([
        'recipe_id' => $recipe->getKey(), 'name' => 'M', 'position' => 1,
    ]), RecipeVariant::class);
    foreach (['Add Milk', 'Add Ice', 'Add Matcha'] as $position => $text) {
        RecipeInstruction::query()->create([
            'recipe_variant_id' => $variant->getKey(), 'position' => $position + 1,
            'type' => $position === 2 ? 'action' : 'ingredient', 'text' => $text,
            'action_key' => $position === 2 ? 'whisk' : 'add', 'icon_group' => 'neutral',
            'is_inferred' => false,
        ]);
    }
    RecipeIngredient::query()->create([
        'recipe_variant_id' => $variant->getKey(), 'position' => 1, 'quantity_value' => 100,
        'quantity_text' => null, 'unit' => 'g', 'name' => 'Milk', 'icon_group' => 'water_milk', 'source_text' => '100g Milk',
    ]);

    $testService = new RecipeTestService();
    $partialAttempt = $testService->start($actor, $worker, $recipe);
    \expect($partialAttempt->getVariantSnapshot()['instructions'][0]['text'] ?? null)->toBe('Add Milk')
        ->and($partialAttempt->getVariantSnapshot()['instructions'][2]['action_key'] ?? null)->toBe('whisk');
    $partialTokens = \collect($partialAttempt->getCorrectStepsSnapshot())->pluck('token')->all();
    \expect($partialAttempt->getPresentedTokens())->not->toBe($partialTokens)
        ->and(fn() => $testService->submit($actor, $partialAttempt, \array_slice($partialTokens, 1)))->toThrow(InvalidArgumentException::class);
    [$partialTokens[0], $partialTokens[1]] = [$partialTokens[1], $partialTokens[0]];
    $partial = $testService->submit($actor, $partialAttempt, $partialTokens);
    \expect($partial->getScore())->toBe(33)
        ->and($partial->isPassed())->toBeFalse();

    $attempt = $testService->start($actor, $worker, $recipe);
    $correctTokens = \collect($attempt->getCorrectStepsSnapshot())->pluck('token')->all();

    RecipeInstruction::query()->where('recipe_variant_id', $variant->getKey())->update(['text' => 'Changed']);
    RecipeIngredient::query()->where('recipe_variant_id', $variant->getKey())->update(['name' => 'Changed ingredient']);
    $submitted = $testService->submit($actor, $attempt, $correctTokens);

    \expect($submitted)->toBeInstanceOf(RecipeTestAttempt::class)
        ->and($submitted->getScore())->toBe(100)
        ->and($submitted->isPassed())->toBeTrue()
        ->and($submitted->getCorrectStepsSnapshot()[0]['text'])->toBe('Add Milk')
        ->and($submitted->getVariantSnapshot()['instructions'][0]['text'] ?? null)->toBe('Add Milk')
        ->and($submitted->getSubmittedAt())->not->toBeNull();

    $workerName = $submitted->getWorkerName();
    $actorName = $submitted->getActorName();
    $worker->delete();
    $actor->delete();
    $preserved = Typer::assertInstance($submitted->fresh(), RecipeTestAttempt::class);
    \expect($preserved->getWorkerId())->toBeNull()
        ->and($preserved->getActorUserId())->toBeNull()
        ->and($preserved->getWorkerName())->toBe($workerName)
        ->and($preserved->getActorName())->toBe($actorName);
});

\test('legacy instruction conversion remains available for existing parsed recipes', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $category = Typer::assertInstance(RecipeCategory::query()->create([
        'user_id' => $admin->getKey(), 'name' => 'FRESH FRUIT TEA', 'position' => 1,
    ]), RecipeCategory::class);
    $recipe = Typer::assertInstance(Recipe::query()->create([
        'user_id' => $admin->getKey(), 'recipe_category_id' => $category->getKey(),
        'name' => 'Legacy Tea', 'note' => null, 'position' => 1, 'archived_at' => null,
    ]), Recipe::class);
    $variant = Typer::assertInstance(RecipeVariant::query()->create([
        'recipe_id' => $recipe->getKey(), 'name' => 'M', 'position' => 1,
    ]), RecipeVariant::class);
    RecipeIngredient::query()->create([
        'recipe_variant_id' => $variant->getKey(), 'position' => 1,
        'quantity_value' => 250, 'quantity_text' => null, 'unit' => 'ml',
        'name' => 'jasmine tea', 'icon_group' => 'tea_matcha', 'source_text' => '250ml jasmine tea',
    ]);
    RecipeStep::query()->create([
        'recipe_variant_id' => $variant->getKey(), 'position' => 1,
        'text' => 'shake', 'action_key' => 'shake', 'source_text' => 'shake',
    ]);
    $admin->setAttribute('recipe_instructions_initialized_at', null);
    $admin->save();

    (new RecipeInstructionService())->initialize($admin);

    $instructions = $variant->instructions()->orderBy('position')->get();
    \expect($instructions)->toHaveCount(2)
        ->and($instructions->first()?->getAttribute('text'))->toBe('Add 250 ml jasmine tea into shaker')
        ->and($instructions->first()?->getAttribute('target'))->toBe('shaker')
        ->and($instructions->last()?->getAttribute('text'))->toBe('Shake')
        ->and($instructions->every(static fn(RecipeInstruction $instruction): bool => $instruction->isInferred()))->toBeTrue();
});
