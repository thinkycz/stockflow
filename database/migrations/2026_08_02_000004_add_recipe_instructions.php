<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Add the canonical ordered recipe sequence while retaining legacy content.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('users', static function (Blueprint $table): void {
            $table->timestamp('recipe_instructions_initialized_at')->nullable()->after('recipes_initialized_at');
        });

        Resolver::resolveSchemaBuilder()->create('recipe_instructions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipe_variant_id')->constrained('recipe_variants')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('type', 32);
            $table->string('text', 1000);
            $table->string('action_key', 32)->default('other');
            $table->decimal('quantity_value', 12, 3)->nullable();
            $table->string('quantity_text', 80)->nullable();
            $table->string('unit', 32)->nullable();
            $table->string('ingredient_name', 180)->nullable();
            $table->string('target', 180)->nullable();
            $table->string('icon_group', 32)->default('neutral');
            $table->string('source_text', 1000)->nullable();
            $table->boolean('is_inferred')->default(false);
            $table->timestamps();
            $table->index(['recipe_variant_id', 'position']);
        });
    }
};
