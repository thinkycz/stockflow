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
        Resolver::resolveSchemaBuilder()->create('inventory_sessions', static function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('counted_at');

            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'store_id', 'counted_at']);
        });
    }
};
