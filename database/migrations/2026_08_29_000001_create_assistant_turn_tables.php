<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->create('assistant_turns', static function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->unsignedBigInteger('actor_user_id')->index();
            $table->string('conversation_id', 36)->index();
            $table->string('kind', 20);
            $table->string('status', 30)->index();
            $table->string('input_hash', 64);
            $table->mediumText('input_payload')->nullable();
            $table->text('error_summary')->nullable();
            $table->dateTime('queued_at');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancel_requested_at')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'status', 'created_at'], 'assistant_turn_conversation_status_index');
        });

        Resolver::resolveSchemaBuilder()->create('assistant_turn_events', static function (Blueprint $table): void {
            $table->id();
            $table->string('turn_id', 36)->index();
            $table->unsignedInteger('sequence');
            $table->string('event_type', 50)->index();
            $table->mediumText('payload');
            $table->timestamps();
            $table->unique(['turn_id', 'sequence']);
        });

        Resolver::resolveSchemaBuilder()->create('assistant_conversation_summaries', static function (Blueprint $table): void {
            $table->id();
            $table->string('conversation_id', 36)->unique();
            $table->string('through_message_id', 36);
            $table->mediumText('summary');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Resolver::resolveSchemaBuilder()->dropIfExists('assistant_conversation_summaries');
        Resolver::resolveSchemaBuilder()->dropIfExists('assistant_turn_events');
        Resolver::resolveSchemaBuilder()->dropIfExists('assistant_turns');
    }
};
