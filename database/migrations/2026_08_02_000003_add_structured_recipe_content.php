<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Add structured recipe ingredients, action metadata and immutable attempt snapshots.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('recipe_steps', static function (Blueprint $table): void {
            $table->string('action_key', 32)->default('other')->after('text');
            $table->string('source_text', 1000)->nullable()->after('action_key');
        });

        Resolver::resolveSchemaBuilder()->create('recipe_ingredients', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipe_variant_id')->constrained('recipe_variants')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->decimal('quantity_value', 12, 3)->nullable();
            $table->string('quantity_text', 80)->nullable();
            $table->string('unit', 32)->nullable();
            $table->string('name', 180);
            $table->string('icon_group', 32)->default('neutral');
            $table->string('source_text', 1000);
            $table->timestamps();
            $table->index(['recipe_variant_id', 'position']);
        });

        Resolver::resolveSchemaBuilder()->table('recipe_test_attempts', static function (Blueprint $table): void {
            $table->json('variant_snapshot')->nullable()->after('correct_steps');
        });
    }
};
