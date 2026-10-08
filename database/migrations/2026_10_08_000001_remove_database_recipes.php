<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Recipes now live in Markdown. Retire catalog content and staff-test data.
     */
    public function up(): void
    {
        $schema = Resolver::resolveSchemaBuilder();
        foreach (['recipe_test_attempts', 'recipe_test_sessions', 'recipe_instructions', 'recipe_ingredients', 'recipe_steps', 'recipe_variants', 'recipes', 'recipe_categories'] as $table) {
            $schema->dropIfExists($table);
        }
        $schema->table('users', static function (Blueprint $table): void {
            $table->dropColumn(['recipes_initialized_at', 'recipe_instructions_initialized_at', 'recipe_catalog_v2_seeded_at']);
        });
    }
};
