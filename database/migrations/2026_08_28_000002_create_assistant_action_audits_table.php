<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Create the supplemental assistant action audit ledger.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->create('assistant_action_audits', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('actor_user_id')->index();
            $table->string('actor_email');
            $table->string('conversation_id', 36)->index();
            $table->string('invocation_id', 36)->index();
            $table->string('tool_call_id')->nullable();
            $table->string('tool_invocation_id', 36)->nullable()->unique();
            $table->string('tool_name');
            $table->string('domain', 50);
            $table->string('operation', 100)->nullable();
            $table->string('classification', 30);
            $table->string('status', 30)->index();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->string('store_name')->nullable();
            $table->string('target_type', 100)->nullable();
            $table->string('target_id', 100)->nullable();
            $table->json('arguments');
            $table->json('result_summary')->nullable();
            $table->text('error_summary')->nullable();
            $table->dateTime('proposed_at')->index();
            $table->dateTime('decided_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->double('duration_ms')->nullable();
            $table->timestamps();
            $table->unique(['conversation_id', 'tool_call_id'], 'assistant_audit_conversation_tool_call_unique');
        });
    }

    /**
     * Drop the supplemental assistant action audit ledger.
     */
    public function down(): void
    {
        Resolver::resolveSchemaBuilder()->dropIfExists('assistant_action_audits');
    }
};
