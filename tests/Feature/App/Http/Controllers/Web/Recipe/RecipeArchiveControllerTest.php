<?php

declare(strict_types=1);

use App\Models\Recipe;
use App\Models\User;
use App\Services\RecipeCatalogService;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('admin archives and restores a recipe', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    (new RecipeCatalogService())->initialize($admin);
    $recipe = Typer::assertInstance(Recipe::query()->firstOrFail(), Recipe::class);

    $this->be($admin, 'users')->put('/recipes/' . $recipe->getKey() . '/archive', ['archived' => true])->assertRedirect();
    \expect($recipe->fresh()?->isArchived())->toBeTrue();
    $this->be($admin, 'users')->put('/recipes/' . $recipe->getKey() . '/archive', ['archived' => false])->assertRedirect();
    \expect($recipe->fresh()?->isArchived())->toBeFalse();
});
