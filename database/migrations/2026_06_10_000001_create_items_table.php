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
        Resolver::resolveSchemaBuilder()->create('items', static function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('title')->index();

            $table->string('sku')->nullable()->unique();

            $table->string('unit')->nullable();

            $table->integer('current_quantity')->default(0);

            $table->decimal('purchase_price', 12, 2)->default(0);

            $table->text('description')->nullable();

            $table->timestamps();
        });
    }
};
