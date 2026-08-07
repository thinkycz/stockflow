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
        Resolver::resolveSchemaBuilder()->create('shift_requests', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('workers')->restrictOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
            $table->unique(['store_id', 'worker_id', 'date']);
            $table->index(['store_id', 'date']);
        });

        Resolver::resolveSchemaBuilder()->create('shift_request_month_locks', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->timestamp('locked_at');
            $table->foreignId('locked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['store_id', 'year', 'month']);
        });
    }
};
