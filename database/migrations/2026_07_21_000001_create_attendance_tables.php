<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->create('attendance_sessions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('workers')->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('active_worker_id')->nullable()->unique()->constrained('workers')->restrictOnDelete();
            $table->date('scheduled_date')->nullable();
            $table->time('scheduled_start_time')->nullable();
            $table->time('scheduled_end_time')->nullable();
            $table->decimal('hourly_rate', 10, 2);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['store_id', 'started_at']);
            $table->index(['worker_id', 'started_at']);
        });

        Resolver::resolveSchemaBuilder()->create('attendance_breaks', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('active_session_id')->nullable()->unique()->constrained('attendance_sessions')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['attendance_session_id', 'started_at']);
        });

        Resolver::resolveSchemaBuilder()->create('attendance_audits', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->text('reason')->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->timestamps();
            $table->index(['attendance_session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Resolver::resolveSchemaBuilder()->dropIfExists('attendance_audits');
        Resolver::resolveSchemaBuilder()->dropIfExists('attendance_breaks');
        Resolver::resolveSchemaBuilder()->dropIfExists('attendance_sessions');
    }
};
