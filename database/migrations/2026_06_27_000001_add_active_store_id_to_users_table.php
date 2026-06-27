<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('users', static function (Blueprint $table): void {
            $table->foreignId('active_store_id')
                ->nullable()
                ->after('assigned_store_id')
                ->constrained('stores')
                ->nullOnDelete();

            $table->index('active_store_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Resolver::resolveSchemaBuilder()->table('users', static function (Blueprint $table): void {
            $table->dropForeign(['active_store_id']);
            $table->dropIndex(['active_store_id']);
            $table->dropColumn('active_store_id');
        });
    }
};
