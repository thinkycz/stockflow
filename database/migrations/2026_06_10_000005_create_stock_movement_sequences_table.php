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
        Resolver::resolveSchemaBuilder()->create('stock_movement_sequences', static function (Blueprint $table): void {
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('type');

            $table->smallInteger('year');

            $table->unsignedInteger('last_number')->default(0);

            $table->primary(['user_id', 'type', 'year']);
        });
    }
};
