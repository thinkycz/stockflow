<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('users', static function (Blueprint $table): void {
            $table->timestamp('recipes_initialized_at')->nullable();
        });

        Resolver::resolveSchemaBuilder()->create('recipe_categories', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'position']);
        });

        Resolver::resolveSchemaBuilder()->create('recipes', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipe_category_id')->constrained('recipe_categories')->restrictOnDelete();
            $table->string('name', 180);
            $table->text('note')->nullable();
            $table->unsignedInteger('position');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'archived_at', 'position']);
            $table->index(['recipe_category_id', 'position']);
        });

        Resolver::resolveSchemaBuilder()->create('recipe_variants', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->string('name', 80)->nullable();
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->index(['recipe_id', 'position']);
        });

        Resolver::resolveSchemaBuilder()->create('recipe_steps', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipe_variant_id')->constrained('recipe_variants')->cascadeOnDelete();
            $table->string('text', 1000);
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->index(['recipe_variant_id', 'position']);
        });

        Resolver::resolveSchemaBuilder()->create('recipe_test_attempts', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained('recipes')->restrictOnDelete();
            $table->foreignId('recipe_variant_id')->nullable()->constrained('recipe_variants')->nullOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained('workers')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipe_name', 180);
            $table->string('variant_name', 80)->nullable();
            $table->string('worker_name', 180);
            $table->string('actor_name', 180);
            $table->json('correct_steps');
            $table->json('presented_tokens');
            $table->json('submitted_tokens')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->boolean('passed')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'worker_id', 'submitted_at'], 'recipe_attempt_worker_index');
            $table->index(['recipe_id', 'submitted_at']);
            $table->index(['actor_user_id', 'submitted_at']);
        });
    }
};
