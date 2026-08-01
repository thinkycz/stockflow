<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('stores', static function (Blueprint $table): void {
            $table->timestamp('checklists_initialized_at')->nullable();
        });

        Resolver::resolveSchemaBuilder()->create('checklist_template_tasks', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('scope', 16);
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->string('shift', 16);
            $table->string('text', 500);
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->index(['user_id', 'store_id']);
            $table->index(['store_id', 'scope', 'weekday', 'shift', 'position'], 'checklist_template_listing_index');
        });

        Resolver::resolveSchemaBuilder()->create('checklist_days', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('excused_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('excuse_reason')->nullable();
            $table->timestamp('excused_at')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'date']);
            $table->index(['user_id', 'date']);
        });

        Resolver::resolveSchemaBuilder()->create('checklist_items', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checklist_day_id')->constrained('checklist_days')->cascadeOnDelete();
            $table->foreignId('template_task_id')->nullable()->constrained('checklist_template_tasks')->nullOnDelete();
            $table->string('shift', 16);
            $table->string('text', 500);
            $table->unsignedInteger('position');
            $table->foreignId('completed_by_worker_id')->nullable()->constrained('workers')->nullOnDelete();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->index(['checklist_day_id', 'shift', 'position']);
            $table->index(['completed_by_worker_id', 'completed_at']);
        });

        Resolver::resolveSchemaBuilder()->create('checklist_events', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checklist_day_id')->constrained('checklist_days')->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->nullable()->constrained('checklist_items')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained('workers')->nullOnDelete();
            $table->string('action', 32);
            $table->text('reason')->nullable();
            $table->timestamp('created_at');
            $table->index(['checklist_day_id', 'created_at']);
        });
    }
};
