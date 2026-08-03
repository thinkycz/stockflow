<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Create the transactional operational activity journal.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->create('operational_activities', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 80);
            $table->string('actor_email');
            $table->dateTimeTz('occurred_at');
            $table->string('url', 2048);
            $table->json('store_contexts');
            $table->json('facts');
            $table->timestamps();

            $table->index(['company_user_id', 'occurred_at'], 'operational_activities_company_occurred_index');
        });
    }
};
