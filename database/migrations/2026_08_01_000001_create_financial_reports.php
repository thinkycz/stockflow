<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->create('financial_reports', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('status')->default('open');
            $table->json('snapshot')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'store_id', 'year', 'month']);
        });

        Resolver::resolveSchemaBuilder()->create('financial_report_overrides', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_report_id')->constrained('financial_reports')->cascadeOnDelete();
            $table->string('source_type');
            $table->string('source_key');
            $table->decimal('amount', 14, 2);
            $table->timestamps();
            $table->unique(['financial_report_id', 'source_type', 'source_key'], 'financial_report_override_source_unique');
        });

        Resolver::resolveSchemaBuilder()->create('financial_report_manual_rows', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_report_id')->constrained('financial_reports')->cascadeOnDelete();
            $table->string('direction');
            $table->string('label', 160);
            $table->date('occurred_on');
            $table->decimal('amount', 14, 2);
            $table->text('note')->nullable();
            $table->foreignId('copied_from_row_id')->nullable()->constrained('financial_report_manual_rows')->nullOnDelete();
            $table->timestamps();
            $table->unique(['financial_report_id', 'copied_from_row_id'], 'financial_report_manual_copy_unique');
        });
    }
};
