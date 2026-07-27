<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Resolver::resolveSchemaBuilder()->table('store_items', static function (Blueprint $table): void {
                $table->decimal('quantity', 18, 3)->default(0)->change();
            });
            Resolver::resolveSchemaBuilder()->table('stock_movement_items', static function (Blueprint $table): void {
                $table->decimal('quantity', 18, 3)->nullable()->change();
                $table->decimal('quantity_before', 18, 3)->nullable()->change();
                $table->decimal('quantity_after', 18, 3)->nullable()->change();
                $table->decimal('quantity_difference', 18, 3)->nullable()->change();
            });
            Resolver::resolveSchemaBuilder()->table('inventory_session_items', static function (Blueprint $table): void {
                $table->decimal('quantity', 18, 3)->change();
                $table->decimal('opening_quantity', 18, 3)->nullable()->change();
                $table->decimal('expected_quantity', 18, 3)->nullable()->change();
                $table->decimal('quantity_difference', 18, 3)->nullable()->change();
            });
        }
        Resolver::resolveSchemaBuilder()->table('stock_movements', static function (Blueprint $table): void {
            $table->unsignedInteger('items_count')->default(0)->after('total_quantity');
            $table->timestamp('occurred_at')->nullable(false)->change();
            $table->index(['user_id', 'store_id', 'occurred_at'], 'stock_movements_store_interval_idx');
            $table->index(['user_id', 'source_store_id', 'occurred_at'], 'stock_movements_source_interval_idx');
            $table->index(['user_id', 'type', 'occurred_at'], 'stock_movements_type_interval_idx');
        });

        DB::table('stock_movements')->update([
            'items_count' => DB::raw('(SELECT COUNT(*) FROM stock_movement_items WHERE stock_movement_items.stock_movement_id = stock_movements.id)'),
        ]);
    }
};
