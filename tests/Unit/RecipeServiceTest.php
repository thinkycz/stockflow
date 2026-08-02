<?php

declare(strict_types=1);

use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\RecipeStep;
use App\Models\RecipeTestAttempt;
use App\Models\RecipeVariant;
use App\Models\User;
use App\Models\Worker;
use App\Services\RecipeCatalogService;
use App\Services\RecipeTestService;
use Database\Factories\UserFactory;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Support\Typer;

\test('initializes the complete recipe catalog once without overwriting changes', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $service = new RecipeCatalogService();

    $service->initialize($admin);

    \expect(RecipeCategory::query()->where('user_id', $admin->getKey())->count())->toBe(8)
        ->and(Recipe::query()->where('user_id', $admin->getKey())->count())->toBe(49)
        ->and(Recipe::query()->where('name', 'CLASSIC MATCHA LATTE')->exists())->toBeTrue()
        ->and(Recipe::query()->where('name', 'Creme Brulee')->exists())->toBeTrue();

    $classic = Typer::assertInstance(Recipe::query()->where('name', 'CLASSIC MATCHA LATTE')->with('variants.steps')->firstOrFail(), Recipe::class);
    $creamCheese = Typer::assertInstance(Recipe::query()->where('name', 'Cream Cheese')->with('variants.steps')->firstOrFail(), Recipe::class);
    \expect($classic->getVariants()->map(static fn(RecipeVariant $variant): string|null => $variant->getName())->all())->toBe(['S', 'M'])
        ->and($classic->getVariants()->first()?->getSteps()->map(static fn(RecipeStep $step): string => $step->getText())->all())->toBe(['100g milk + 20g sugar - stir', 'ice', '50g water (70-80 degrees) + 3,5g matcha'])
        ->and($creamCheese->getNote())->toBe('use up in 5 days')
        ->and($creamCheese->getVariants()->map(static fn(RecipeVariant $variant): string|null => $variant->getName())->all())->toBe(['Batch', '1 portion'])
        ->and($creamCheese->getVariants()->last()?->getSteps()->map(static fn(RecipeStep $step): string => $step->getText())->all())->toBe(['50g smetana + 20g salko + 20g milk + 10g cheese', 'whip up']);

    Recipe::query()->where('name', 'CLASSIC MATCHA LATTE')->update(['name' => 'Edited recipe']);
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
    foreach (['Milk', 'Ice', 'Matcha'] as $position => $text) {
        RecipeStep::query()->create([
            'recipe_variant_id' => $variant->getKey(), 'text' => $text, 'position' => $position + 1,
        ]);
    }

    $testService = new RecipeTestService();
    $partialAttempt = $testService->start($actor, $worker, $recipe);
    $partialTokens = \collect($partialAttempt->getCorrectStepsSnapshot())->pluck('token')->all();
    \expect($partialAttempt->getPresentedTokens())->not->toBe($partialTokens)
        ->and(fn() => $testService->submit($actor, $partialAttempt, \array_slice($partialTokens, 1)))->toThrow(InvalidArgumentException::class);
    [$partialTokens[0], $partialTokens[1]] = [$partialTokens[1], $partialTokens[0]];
    $partial = $testService->submit($actor, $partialAttempt, $partialTokens);
    \expect($partial->getScore())->toBe(33)
        ->and($partial->isPassed())->toBeFalse();

    $attempt = $testService->start($actor, $worker, $recipe);
    $correctTokens = \collect($attempt->getCorrectStepsSnapshot())->pluck('token')->all();

    RecipeStep::query()->where('recipe_variant_id', $variant->getKey())->update(['text' => 'Changed']);
    $submitted = $testService->submit($actor, $attempt, $correctTokens);

    \expect($submitted)->toBeInstanceOf(RecipeTestAttempt::class)
        ->and($submitted->getScore())->toBe(100)
        ->and($submitted->isPassed())->toBeTrue()
        ->and($submitted->getCorrectStepsSnapshot()[0]['text'])->toBe('Milk')
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
