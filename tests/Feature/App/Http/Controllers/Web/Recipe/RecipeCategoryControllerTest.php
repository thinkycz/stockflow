<?php

declare(strict_types=1);

use App\Models\RecipeCategory;
use App\Models\User;
use App\Services\RecipeCatalogService;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('admin manages empty recipe categories and limited account is refused', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $this->be($admin, 'users')->post('/recipe-categories', ['name' => 'Seasonal'])->assertRedirect();
    $category = Typer::assertInstance(RecipeCategory::query()->where('name', 'Seasonal')->firstOrFail(), RecipeCategory::class);
    $this->be($admin, 'users')->put('/recipe-categories/' . $category->getKey(), ['name' => 'Summer'])->assertRedirect();
    $this->be($admin, 'users')->delete('/recipe-categories/' . $category->getKey())->assertRedirect();
    $this->assertDatabaseMissing('recipe_categories', ['id' => $category->getKey()]);

    $limited = UserFactory::new()->createOne(['parent_user_id' => $admin->getKey()]);
    $this->be($limited, 'users')->post('/recipe-categories', ['name' => 'Forbidden'])->assertRedirect('/dashboard');
});

\test('admin changes recipe category order', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    (new RecipeCatalogService())->initialize($admin);
    $first = Typer::assertInstance(RecipeCategory::query()->where('user_id', $admin->getKey())->orderBy('position')->firstOrFail(), RecipeCategory::class);
    $second = Typer::assertInstance(RecipeCategory::query()->where('user_id', $admin->getKey())->orderBy('position')->skip(1)->firstOrFail(), RecipeCategory::class);

    $this->be($admin, 'users')->put('/recipe-categories/' . $second->getKey() . '/position', ['direction' => 'up'])->assertRedirect();

    \expect($first->fresh()?->getPosition())->toBe(2)
        ->and($second->fresh()?->getPosition())->toBe(1);
});
