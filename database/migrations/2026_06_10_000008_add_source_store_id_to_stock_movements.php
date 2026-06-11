<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('stock_movements', static function (Blueprint $table): void {
            $table->foreignId('source_store_id')
                ->nullable()
                ->after('store_id')
                ->constrained('stores')
                ->nullOnDelete();

            $table->index('source_store_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Resolver::resolveSchemaBuilder()->table('stock_movements', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_store_id');
        });
    }
};
