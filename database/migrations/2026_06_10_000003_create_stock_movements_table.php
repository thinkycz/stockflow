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
        Resolver::resolveSchemaBuilder()->create('stock_movements', static function (Blueprint $table): void {
            $table->id();

            $table->string('number');

            $table->string('type');

            $table->foreignId('store_id')
                ->nullable()
                ->constrained('stores')
                ->nullOnDelete();

            $table->date('movement_date');

            $table->text('note')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->decimal('total_quantity', 14, 3)->default(0);

            $table->decimal('total_value', 14, 2)->default(0);

            $table->timestamps();

            $table->unique('number');

            $table->index(['type', 'movement_date']);

            $table->index('store_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Resolver::resolveSchemaBuilder()->dropIfExists('stock_movements');
    }
};
