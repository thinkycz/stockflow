<?php

declare(strict_types=1);

use App\Models\Recipe;
use App\Models\RecipeInstruction;
use App\Models\RecipeTestAttempt;
use App\Models\User;
use App\Services\RecipeCatalogService;
use Database\Factories\UserFactory;
use Database\Seeders\RecipeCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Support\Typer;

\uses(RefreshDatabase::class);

\it('replaces the deployed legacy catalog once and preserves attempt snapshots', function (): void {
    $owner = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    (new RecipeCatalogService())->initialize($owner);

    $legacyRecipe = Typer::assertInstance(Recipe::query()->where('name', 'CLASSIC MATCHA LATTE')->firstOrFail(), Recipe::class);
    $legacyRecipe->setAttribute('name', 'ADMIN EDITED MATCHA');
    $legacyRecipe->save();
    $attempt = Typer::assertInstance(RecipeTestAttempt::query()->create([
        'user_id' => $owner->getKey(),
        'recipe_id' => $legacyRecipe->getKey(),
        'recipe_variant_id' => null,
        'worker_id' => null,
        'actor_user_id' => $owner->getKey(),
        'recipe_name' => 'CLASSIC MATCHA LATTE',
        'variant_name' => 'S',
        'worker_name' => 'Historical Worker',
        'actor_name' => $owner->getEmail(),
        'correct_steps' => [['token' => 'first', 'text' => 'First'], ['token' => 'second', 'text' => 'Second']],
        'presented_tokens' => ['second', 'first'],
        'submitted_tokens' => ['first', 'second'],
        'score' => 100,
        'passed' => true,
        'started_at' => Carbon::now(),
        'submitted_at' => Carbon::now(),
    ]), RecipeTestAttempt::class);

    (new RecipeCatalogSeeder())->run();

    \expect(Recipe::query()->whereKey($legacyRecipe->getKey())->exists())->toBeFalse()
        ->and($attempt->fresh()?->getAttribute('recipe_id'))->toBeNull()
        ->and($attempt->fresh()?->getCorrectStepsSnapshot())->toBe($attempt->getCorrectStepsSnapshot())
        ->and(Recipe::query()->where('name', 'ADMIN EDITED MATCHA')->exists())->toBeFalse();

    $seededRecipe = Typer::assertInstance(Recipe::query()->where('name', 'CLASSIC MATCHA LATTE')->firstOrFail(), Recipe::class);
    $variant = $seededRecipe->getVariants()->first();
    \expect($variant)->not->toBeNull();
    $instructions = RecipeInstruction::query()->where('recipe_variant_id', $variant?->getKey())->orderBy('position')->pluck('text')->all();
    \expect($instructions)->toBe([
        'Add 100 ml milk into cup',
        'Add 20 g sugar into cup',
        'Stir',
        'Add Ice into cup',
        'Add 50 g water into matcha bowl',
        'Add 3,5 g matcha into matcha bowl',
        'Use Matcha Whisk',
        'Pour into cup',
    ]);

    $recipeIds = Recipe::query()->orderBy('id')->pluck('id')->all();
    (new RecipeCatalogSeeder())->run();
    \expect(Recipe::query()->orderBy('id')->pluck('id')->all())->toBe($recipeIds);
});
