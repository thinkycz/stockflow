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

            $table->integer('quantity')->nullable();

            $table->decimal('total', 14, 2)->default(0);

            $table->integer('quantity_before')->nullable();

            $table->integer('quantity_after')->nullable();

            $table->integer('quantity_difference')->nullable();

            $table->string('adjustment_reason')->nullable();

            $table->index(['stock_movement_id', 'item_id']);
        });
    }
};
