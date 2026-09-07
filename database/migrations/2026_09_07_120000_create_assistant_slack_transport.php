<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Create durable Slack bindings, delivery journals and decision claims.
     */
    public function up(): void
    {
        $schema = Resolver::resolveSchemaBuilder();
        $schema->create('assistant_slack_threads', static function (Blueprint $table): void {
            $table->id();
            $table->string('workspace_id', 40);
            $table->string('channel_id', 40);
            $table->string('thread_ts', 40);
            $table->uuid('conversation_id')->nullable()->unique();
            $table->unsignedBigInteger('admin_user_id');
            $table->unsignedBigInteger('active_store_id')->nullable();
            $table->string('mapping_status', 30)->default('general');
            $table->string('activation_ts', 40);
            $table->text('thread_url')->nullable();
            $table->boolean('history_ready')->default(false);
            $table->text('history_error')->nullable();
            $table->timestamp('detached_at')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'channel_id', 'thread_ts'], 'assistant_slack_thread_binding');
        });
        $schema->create('assistant_slack_events', static function (Blueprint $table): void {
            $table->id();
            $table->string('delivery_key', 64)->unique();
            $table->string('workspace_id', 40);
            $table->string('channel_id', 40);
            $table->string('thread_ts', 40);
            $table->string('message_ts', 40);
            $table->string('author_id', 40);
            $table->string('kind', 30);
            $table->boolean('activates')->default(false);
            $table->longText('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('available_at')->nullable()->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();
        });
        $schema->create('assistant_slack_history_pages', static function (Blueprint $table): void {
            $table->string('page_key', 64)->primary();
            $table->string('scan_id', 100)->index();
            $table->longText('payload');
            $table->timestamps();
        });
        $schema->create('assistant_slack_inputs', static function (Blueprint $table): void {
            $table->id();
            $table->uuid('conversation_id')->index();
            $table->uuid('turn_id')->unique();
            $table->string('origin', 20);
            $table->string('author_id', 40);
            $table->timestamps();
        });
        $schema->create('assistant_decision_claims', static function (Blueprint $table): void {
            $table->id();
            $table->uuid('conversation_id');
            $table->string('tool_call_id', 191);
            $table->string('action', 20)->nullable();
            $table->string('option_id', 100)->nullable();
            $table->uuid('turn_id');
            $table->string('origin', 20);
            $table->string('author_id', 40);
            $table->timestamps();
            $table->unique(['conversation_id', 'tool_call_id'], 'assistant_decision_winner');
        });
        $schema->create('assistant_slack_outbox', static function (Blueprint $table): void {
            $table->id();
            $table->uuid('conversation_id')->index();
            $table->string('delivery_key', 64)->unique();
            $table->longText('payload');
            $table->string('message_ts', 40)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('available_at')->nullable()->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }
};
