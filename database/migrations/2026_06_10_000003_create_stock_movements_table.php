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

            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('store_id')
                ->nullable()
                ->constrained('stores')
                ->nullOnDelete();

            $table->foreignId('source_store_id')
                ->nullable()
                ->after('store_id')
                ->constrained('stores')
                ->nullOnDelete();

            $table->index('source_store_id');

            $table->text('note')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->integer('total_quantity')->default(0);

            $table->decimal('total_value', 14, 2)->default(0);

            $table->timestamps();

            $table->unique('number');

            $table->index('store_id');
        });
    }
};
