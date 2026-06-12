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
        Resolver::resolveSchemaBuilder()->create('stores', static function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->boolean('is_warehouse')->default(false)->after('status');

            $table->string('name');

            $table->text('address')->nullable();

            $table->string('status')->default('active');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('status');
        });

        Resolver::resolveSchemaBuilder()->create('store_items', static function (Blueprint $table): void {
            $table->id();

            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();

            $table->foreignId('item_id')
                ->constrained('items')
                ->restrictOnDelete();

            $table->integer('quantity')->default(0);

            $table->unique(['store_id', 'item_id']);
            $table->index('item_id');
        });
    }
};
