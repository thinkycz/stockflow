<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Allow a deploy seed to replace recipes while retaining immutable attempt snapshots.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('users', static function (Blueprint $table): void {
            $table->timestamp('recipe_catalog_v2_seeded_at')->nullable()->after('recipe_instructions_initialized_at');
        });

        Resolver::resolveSchemaBuilder()->table('recipe_test_attempts', static function (Blueprint $table): void {
            $table->dropForeign(['recipe_id']);
            $table->unsignedBigInteger('recipe_id')->nullable()->change();
            $table->foreign('recipe_id')->references('id')->on('recipes')->nullOnDelete();
        });
    }
};
