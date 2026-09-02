<?php

declare(strict_types=1);

use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\RecipeInstruction;
use App\Models\RecipeTestAttempt;
use App\Models\RecipeVariant;
use App\Models\User;
use App\Services\RecipeCatalogService;
use Database\Factories\UserFactory;
use Database\Seeders\RecipeCatalogSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Support\Typer;

\uses(RefreshDatabase::class);

\it('replaces the deployed legacy catalog once and preserves attempt snapshots', function (): void {
    $owner = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    (new RecipeCatalogService())->initialize($owner);

    $legacyRecipe = Typer::assertInstance(Recipe::query()->where('name', 'Classic Matcha Latte')->firstOrFail(), Recipe::class);
    $legacyVariant = Typer::assertInstance($legacyRecipe->getVariants()->firstOrFail(), RecipeVariant::class);
    $legacyRecipe->setAttribute('name', 'ADMIN EDITED MATCHA');
    $legacyRecipe->save();
    $attempt = Typer::assertInstance(RecipeTestAttempt::query()->create([
        'user_id' => $owner->getKey(),
        'recipe_id' => $legacyRecipe->getKey(),
        'recipe_variant_id' => $legacyVariant->getKey(),
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
        ->and($attempt->fresh()?->getAttribute('recipe_variant_id'))->toBeNull()
        ->and($attempt->fresh()?->getCorrectStepsSnapshot())->toBe($attempt->getCorrectStepsSnapshot())
        ->and(Recipe::query()->where('name', 'ADMIN EDITED MATCHA')->exists())->toBeFalse();

    $seededRecipe = Typer::assertInstance(Recipe::query()->where('name', 'Classic Matcha Latte')->firstOrFail(), Recipe::class);
    $variant = $seededRecipe->getVariants()->first();
    \expect($variant)->not->toBeNull();
    $instructions = RecipeInstruction::query()->where('recipe_variant_id', $variant?->getKey())->orderBy('position')->pluck('text')->all();
    \expect($instructions)->toBe([
        'Add 100 ml milk to serving cup.',
        'Add 20 ml liquid sugar to serving cup.',
        'Stir until combined.',
        'Fill the serving cup with ice.',
        'Add 50 ml water at 70–80 °C to matcha bowl.',
        'Add 3.5 g matcha to matcha bowl.',
        'Whisk until smooth.',
        'Pour the matcha into the serving cup.',
    ])->and(RecipeVariant::query()->count())->toBe(184);

    $recipeIds = Recipe::query()->orderBy('id')->pluck('id')->all();
    (new RecipeCatalogSeeder())->run();
    \expect(Recipe::query()->orderBy('id')->pluck('id')->all())->toBe($recipeIds);
});

\it('force replaces every recipe even when the previous deploy marker is already set', function (): void {
    $owner = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    (new RecipeCatalogService())->initialize($owner);
    $owner->setAttribute('recipe_catalog_v2_seeded_at', Carbon::now());
    $owner->save();

    Recipe::query()->where('name', 'Classic Matcha Latte')->update(['name' => 'STALE PRODUCTION MATCHA']);
    Recipe::query()->where('name', 'Coconut Cloud')->delete();

    $migration = Typer::assertInstance(require \database_path('migrations/2026_09_02_000001_force_replace_reviewed_recipe_catalog.php'), Migration::class);
    $migration->up();

    \expect(RecipeCategory::query()->count())->toBe(8)
        ->and(Recipe::query()->count())->toBe(49)
        ->and(RecipeVariant::query()->count())->toBe(184)
        ->and(RecipeVariant::query()->has('instructions', '<', 2)->count())->toBe(0)
        ->and(RecipeInstruction::query()->where('is_inferred', true)->count())->toBe(0)
        ->and(Recipe::query()->where('name', 'STALE PRODUCTION MATCHA')->exists())->toBeFalse()
        ->and(Recipe::query()->where('name', 'Classic Matcha Latte')->exists())->toBeTrue()
        ->and(Recipe::query()->where('name', 'Coconut Cloud')->exists())->toBeTrue();
});
