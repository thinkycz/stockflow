<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * The price of a stock movement row is now always the item's
     * `purchase_price` at the time of the move (the item is the
     * single source of truth for price), so the per-row `unit_price`
     * column is redundant. The `total` column is preserved.
     *
     * The `movement_date` column is also redundant: it was always
     * populated with the creation timestamp, and the create form
     * no longer exposes it. `created_at` takes over everywhere — the
     * `(type, created_at)` index replaces the old `(type, movement_date)`.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('stock_movements', static function (Blueprint $table): void {
            $table->dropIndex(['type', 'movement_date']);
            $table->dropColumn('movement_date');
            $table->index(['type', 'created_at']);
        });

        Resolver::resolveSchemaBuilder()->table('stock_movement_items', static function (Blueprint $table): void {
            $table->dropColumn('unit_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Resolver::resolveSchemaBuilder()->table('stock_movement_items', static function (Blueprint $table): void {
            $table->decimal('unit_price', 12, 2)->default(0);
        });

        Resolver::resolveSchemaBuilder()->table('stock_movements', static function (Blueprint $table): void {
            $table->date('movement_date');
            $table->dropIndex(['type', 'created_at']);
            $table->index(['type', 'movement_date']);
        });
    }
};
