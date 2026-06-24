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
        Resolver::resolveSchemaBuilder()->create('inventory_session_items', static function (Blueprint $table): void {
            $table->id();

            $table->foreignId('session_id')
                ->constrained('inventory_sessions')
                ->cascadeOnDelete();

            $table->foreignId('item_id')
                ->constrained('items')
                ->restrictOnDelete();

            $table->unsignedInteger('quantity');

            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['session_id', 'item_id']);
        });
    }
};
