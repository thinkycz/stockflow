<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\Worker;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

\test('recipe retirement drops populated catalog and test tables while preserving operational data', function (): void {
    [$admin, $warehouse] = \createIsolatedUserWithWarehouse();
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $item = Item::factory()->create(['user_id' => $admin->getKey()]);
    foreach (['2026_08_02_000002_create_recipe_tables.php', '2026_08_02_000003_add_structured_recipe_content.php', '2026_08_02_000004_add_recipe_instructions.php', '2026_08_02_000005_prepare_recipe_catalog_v2_seed.php', '2026_08_02_000007_create_recipe_test_sessions.php'] as $file) {
        Typer::assertInstance(require \database_path('migrations/' . $file), Migration::class)->up();
    }
    $category = DB::table('recipe_categories')->insertGetId(['user_id' => $admin->getKey(), 'name' => 'OLD', 'position' => 1]);
    $recipe = DB::table('recipes')->insertGetId(['user_id' => $admin->getKey(), 'recipe_category_id' => $category, 'name' => 'Old recipe', 'position' => 1]);
    $variant = DB::table('recipe_variants')->insertGetId(['recipe_id' => $recipe, 'name' => 'S', 'position' => 1]);
    DB::table('recipe_steps')->insert(['recipe_variant_id' => $variant, 'text' => 'Stir.', 'position' => 1]);
    DB::table('recipe_ingredients')->insert(['recipe_variant_id' => $variant, 'name' => 'milk', 'quantity_value' => 100, 'unit' => 'ml', 'position' => 1, 'source_text' => '100 ml milk']);
    DB::table('recipe_instructions')->insert(['recipe_variant_id' => $variant, 'type' => 'action', 'text' => 'Stir.', 'position' => 1]);
    $session = DB::table('recipe_test_sessions')->insertGetId(['user_id' => $admin->getKey(), 'worker_id' => $worker->getKey(), 'actor_user_id' => $admin->getKey(), 'worker_name' => 'Employee', 'actor_name' => 'Admin', 'started_at' => '2026-10-01 12:00:00', 'submitted_at' => '2026-10-01 12:01:00', 'passed' => true, 'score' => 100]);
    DB::table('recipe_test_attempts')->insert(['recipe_test_session_id' => $session, 'user_id' => $admin->getKey(), 'recipe_id' => $recipe, 'recipe_variant_id' => $variant, 'worker_id' => $worker->getKey(), 'actor_user_id' => $admin->getKey(), 'recipe_name' => 'Old recipe', 'worker_name' => 'Employee', 'actor_name' => 'Admin', 'correct_steps' => '[]', 'presented_tokens' => '[]', 'started_at' => '2026-10-01 12:00:00']);
    DB::table('users')->where('id', $admin->getKey())->update(['recipes_initialized_at' => '2026-10-01 12:00:00', 'recipe_instructions_initialized_at' => '2026-10-01 12:00:00', 'recipe_catalog_v2_seeded_at' => '2026-10-01 12:00:00']);

    Typer::assertInstance(require \database_path('migrations/2026_10_08_000001_remove_database_recipes.php'), Migration::class)->up();
    foreach (['recipe_categories', 'recipes', 'recipe_variants', 'recipe_ingredients', 'recipe_steps', 'recipe_instructions', 'recipe_test_attempts', 'recipe_test_sessions'] as $table) {
        \expect(Resolver::resolveSchemaBuilder()->hasTable($table))->toBeFalse();
    }
    foreach (['recipes_initialized_at', 'recipe_instructions_initialized_at', 'recipe_catalog_v2_seeded_at'] as $column) {
        \expect(Resolver::resolveSchemaBuilder()->hasColumn('users', $column))->toBeFalse();
    }
    $this->assertDatabaseHas('users', ['id' => $admin->getKey()]);
    $this->assertDatabaseHas('stores', ['id' => $warehouse->getKey()]);
    $this->assertDatabaseHas('workers', ['id' => $worker->getKey()]);
    $this->assertDatabaseHas('items', ['id' => $item->getKey()]);
});
