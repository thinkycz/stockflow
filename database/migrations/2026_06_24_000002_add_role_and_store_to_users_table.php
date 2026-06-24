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
            $table->boolean('is_admin')->default(false)->after('password');

            $table->foreignId('parent_user_id')
                ->nullable()
                ->after('is_admin')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('assigned_store_id')
                ->nullable()
                ->after('parent_user_id')
                ->constrained('stores')
                ->nullOnDelete();

            $table->index('is_admin');
            $table->index('parent_user_id');
            $table->index('assigned_store_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Resolver::resolveSchemaBuilder()->table('users', static function (Blueprint $table): void {
            $table->dropForeign(['parent_user_id']);
            $table->dropForeign(['assigned_store_id']);
            $table->dropIndex(['is_admin']);
            $table->dropIndex(['parent_user_id']);
            $table->dropIndex(['assigned_store_id']);
            $table->dropColumn(['is_admin', 'parent_user_id', 'assigned_store_id']);
        });
    }
};
