<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->create('attendance_deviation_reviews', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision');
            $table->text('reason');
            $table->timestamp('actual_started_at');
            $table->timestamp('actual_ended_at');
            $table->time('before_start_time');
            $table->time('before_end_time');
            $table->time('after_start_time');
            $table->time('after_end_time');
            $table->timestamps();
            $table->index(['shift_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Resolver::resolveSchemaBuilder()->dropIfExists('attendance_deviation_reviews');
    }
};
