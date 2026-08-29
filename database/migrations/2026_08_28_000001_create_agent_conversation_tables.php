<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Create Laravel AI conversation storage.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->create('agent_conversations', static function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('participant_type')->nullable();
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->string('title');
            $table->timestamps();
            $table->index(['participant_type', 'participant_id', 'updated_at'], 'agent_conversations_participant_updated_at_index');
        });

        Resolver::resolveSchemaBuilder()->create('agent_conversation_messages', static function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('conversation_id', 36)->index();
            $table->string('participant_type')->nullable();
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->string('agent');
            $table->string('role', 25);
            $table->text('content');
            $table->text('attachments');
            $table->text('tool_calls');
            $table->text('tool_results');
            $table->text('usage');
            $table->text('meta');
            $table->text('approval_state')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'participant_type', 'participant_id', 'updated_at'], 'agent_messages_conversation_index');
            $table->index(['participant_type', 'participant_id'], 'agent_messages_participant_index');
        });
    }

    /**
     * Drop Laravel AI conversation storage.
     */
    public function down(): void
    {
        Resolver::resolveSchemaBuilder()->dropIfExists('agent_conversation_messages');
        Resolver::resolveSchemaBuilder()->dropIfExists('agent_conversations');
    }
};
