<?php

declare(strict_types=1);
use App\Enums\LimitedUserSectionEnum;
use App\Models\Store;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

\test('admin and limited accounts browse the same file authored library without recipe SQL', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);
    $queries = [];
    DB::listen(static function (QueryExecuted $query) use (&$queries): void { $queries[] = $query->sql; });
    foreach ([$admin, $limited] as $user) {
        $this->be($user, 'users')->get('/recipes?category=hot-drinks&search=matcha', $this->inertiaHeaders())
            ->assertOk()->assertJsonPath('component', 'recipes/Index')
            ->assertJsonCount(54, 'props.recipes')->assertJsonCount(54, 'props.lookup')
            ->assertJsonCount(9, 'props.categories')
            ->assertJsonPath('props.categories.7.key', 'hot-drinks')
            ->assertJsonPath('props.categories.7.recipe_count', 5)
            ->assertJsonPath('props.recipes.0.name', 'Classic Matcha Latte')
            ->assertJsonPath('props.recipes.0.url', '/recipes/matcha-latte/classic-matcha-latte')
            ->assertJsonPath('props.filters.category', 'hot-drinks')
            ->assertJsonPath('props.filters.search', 'matcha')
            ->assertJsonMissingPath('props.recipes.0.variants')
            ->assertJsonMissingPath('props.workers')
            ->assertJsonMissingPath('props.testable_recipe_count');
    }
    \expect(\array_filter($queries, static fn(string $sql): bool => \preg_match('/\\brecipe(?:s|_[a-z_]+)\\b/i', $sql) === 1))->toBe([])
        ->and(Resolver::resolveSchemaBuilder()->hasTable('recipes'))->toBeFalse();
});

\test('disabled recipe access still blocks the library and individual documents', function (): void {
    $limited = UserFactory::new()->limited(Store::factory()->create())->createOne(['disabled_sections' => [LimitedUserSectionEnum::RECIPES->value]]);
    foreach (['/recipes', '/recipes/hot-drinks/classic-matcha'] as $url) {
        $this->be($limited, 'users')->getJson($url)->assertForbidden();
    }
});

\test('retired recipe editor category and staff test routes no longer exist', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    foreach (['/recipes/create', '/recipes/1/edit', '/recipe-categories', '/recipe-tests/1', '/recipe-test-sessions/1', '/recipe-test-results'] as $url) {
        $this->be($admin, 'users')->get($url)->assertNotFound();
    }
    foreach (['/recipes', '/recipe-categories', '/recipe-test-sessions'] as $url) {
        $this->be($admin, 'users')->post($url, [])->assertStatus($url === '/recipes' ? 405 : 404);
    }
});
