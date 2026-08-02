<?php

declare(strict_types=1);

use App\Support\RecipeNameNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

return new class extends Migration {
    /**
     * Add three-recipe parent sessions, amount scoring, and normalize stored recipe names.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->create('recipe_test_sessions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained('workers')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('worker_name', 180);
            $table->string('actor_name', 180);
            $table->unsignedTinyInteger('score')->nullable();
            $table->boolean('passed')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'worker_id', 'submitted_at'], 'recipe_session_worker_index');
            $table->index(['actor_user_id', 'submitted_at']);
        });

        Resolver::resolveSchemaBuilder()->table('recipe_test_attempts', static function (Blueprint $table): void {
            $table->foreignId('recipe_test_session_id')->nullable()->after('id')->constrained('recipe_test_sessions')->nullOnDelete();
            $table->unsignedTinyInteger('session_position')->nullable()->after('recipe_test_session_id');
            $table->json('submitted_amounts')->nullable()->after('submitted_tokens');
            $table->unsignedTinyInteger('order_score')->nullable()->after('score');
            $table->unsignedTinyInteger('amount_score')->nullable()->after('order_score');
            $table->index(['recipe_test_session_id', 'session_position'], 'recipe_attempt_session_index');
        });

        foreach (DB::table('recipes')->select(['id', 'name'])->orderBy('id')->get() as $recipe) {
            DB::table('recipes')->where('id', Typer::assertInt($recipe->id))->update([
                'name' => RecipeNameNormalizer::normalize(Typer::assertString($recipe->name)),
            ]);
        }
    }
};
