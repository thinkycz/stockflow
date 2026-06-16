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
        Resolver::resolveSchemaBuilder()->create('statement_days', static function (Blueprint $table): void {
            $table->id();

            $table->foreignId('statement_id')
                ->constrained('statements')
                ->cascadeOnDelete();

            $table->date('date');

            $table->decimal('cash', 10, 2)->default(0);
            $table->decimal('card', 10, 2)->default(0);
            $table->decimal('wolt', 10, 2)->default(0);
            $table->decimal('bolt', 10, 2)->default(0);
            $table->decimal('foodora', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            $table->timestamps();

            $table->unique(['statement_id', 'date']);
        });
    }
};
