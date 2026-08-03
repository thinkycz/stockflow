<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Create daily digest persistence and record feature activation.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('users', static function (Blueprint $table): void {
            $table->date('operational_digest_started_on')->nullable();
        });

        Resolver::resolveDatabaseManager()->table('users')
            ->where('is_admin', true)
            ->whereNull('parent_user_id')
            ->update(['operational_digest_started_on' => CarbonImmutable::now('Europe/Prague')->toDateString()]);

        Resolver::resolveSchemaBuilder()->create('operational_daily_digests', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('digest_date');
            $table->dateTimeTz('period_start');
            $table->dateTimeTz('period_end');
            $table->string('status', 20);
            $table->json('snapshot');
            $table->unsignedInteger('activity_count');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->dateTimeTz('queued_at')->nullable();
            $table->dateTimeTz('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['company_user_id', 'digest_date'], 'operational_daily_digests_company_date_unique');
        });
    }
};
