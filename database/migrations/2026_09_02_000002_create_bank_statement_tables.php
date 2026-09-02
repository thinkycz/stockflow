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
        Resolver::resolveSchemaBuilder()->create('bank_statements', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reopened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24);
            $table->string('bank_code', 16)->nullable();
            $table->string('bank_name', 120)->nullable();
            $table->text('account_name')->nullable();
            $table->text('account_number')->nullable();
            $table->text('iban')->nullable();
            $table->string('bic', 32)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('statement_number', 32)->nullable();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->decimal('opening_balance', 15, 2)->nullable();
            $table->decimal('total_credits', 15, 2)->nullable();
            $table->decimal('total_debits', 15, 2)->nullable();
            $table->decimal('closing_balance', 15, 2)->nullable();
            $table->decimal('available_balance', 15, 2)->nullable();
            $table->unsignedInteger('credit_count')->nullable();
            $table->unsignedInteger('debit_count')->nullable();
            $table->string('original_path');
            $table->text('original_name');
            $table->string('original_mime', 100);
            $table->unsignedBigInteger('original_size');
            $table->string('sha256', 64);
            $table->text('parse_warnings')->nullable();
            $table->longText('raw_ai_response')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('parsed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'sha256']);
            $table->unique(
                ['user_id', 'store_id', 'bank_code', 'statement_number', 'period_from', 'period_to'],
                'bank_statements_logical_unique',
            );
            $table->index(['user_id', 'store_id', 'period_from', 'period_to']);
        });

        Resolver::resolveSchemaBuilder()->create('bank_statement_transactions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_statement_id')->constrained('bank_statements')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->date('booked_on');
            $table->date('executed_on')->nullable();
            $table->string('item_type', 160);
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3);
            $table->text('counterparty_name')->nullable();
            $table->text('counterparty_account')->nullable();
            $table->text('variable_symbol')->nullable();
            $table->text('constant_symbol')->nullable();
            $table->text('specific_symbol')->nullable();
            $table->text('description')->nullable();
            $table->string('category', 32);
            $table->date('sales_from')->nullable();
            $table->date('sales_to')->nullable();
            $table->text('review_note')->nullable();
            $table->longText('source_payload')->nullable();
            $table->boolean('manually_edited')->default(false);
            $table->timestamps();

            $table->unique(['bank_statement_id', 'position']);
            $table->index(['bank_statement_id', 'category']);
        });
    }
};
