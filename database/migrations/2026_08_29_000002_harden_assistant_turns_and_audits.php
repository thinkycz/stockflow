<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('assistant_turns', static function (Blueprint $table): void {
            $table->string('parent_turn_id', 36)->nullable()->index()->after('conversation_id');
            $table->string('recovery_mode', 30)->default('normal')->after('kind');
        });
        Resolver::resolveSchemaBuilder()->table('assistant_action_audits', static function (Blueprint $table): void {
            $table->string('turn_id', 36)->nullable()->index()->after('conversation_id');
        });
        Resolver::resolveSchemaBuilder()->table('assistant_conversation_summaries', static function (Blueprint $table): void {
            $table->unsignedSmallInteger('version')->default(2)->after('conversation_id');
        });
    }

    public function down(): void
    {
        Resolver::resolveSchemaBuilder()->table('assistant_conversation_summaries', static function (Blueprint $table): void { $table->dropColumn('version'); });
        Resolver::resolveSchemaBuilder()->table('assistant_action_audits', static function (Blueprint $table): void { $table->dropIndex(['turn_id']);
            $table->dropColumn('turn_id'); });
        Resolver::resolveSchemaBuilder()->table('assistant_turns', static function (Blueprint $table): void { $table->dropIndex(['parent_turn_id']);
            $table->dropColumn(['parent_turn_id', 'recovery_mode']); });
    }
};
