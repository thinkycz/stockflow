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
        Resolver::resolveSchemaBuilder()->create('stock_movement_items', static function (Blueprint $table): void {
            $table->id();

            $table->foreignId('stock_movement_id')
                ->constrained('stock_movements')
                ->cascadeOnDelete();

            $table->foreignId('item_id')
                ->constrained('items')
                ->restrictOnDelete();

            $table->decimal('quantity', 12, 3)->nullable();

            $table->decimal('unit_price', 12, 2)->default(0);

            $table->decimal('total', 14, 2)->default(0);

            $table->decimal('quantity_before', 12, 3)->nullable();

            $table->decimal('quantity_after', 12, 3)->nullable();

            $table->decimal('quantity_difference', 12, 3)->nullable();

            $table->string('adjustment_reason')->nullable();

            $table->index(['stock_movement_id', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Resolver::resolveSchemaBuilder()->dropIfExists('stock_movement_items');
    }
};
