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
        $schema = Resolver::resolveSchemaBuilder();

        if (!$schema->hasTable('financial_recurring_expenses')) {
            $schema->create('financial_recurring_expenses', static function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
                $table->date('starts_on');
                $table->date('ends_before')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'store_id', 'starts_on']);
            });
        }

        if (!$schema->hasTable('financial_recurring_expense_versions')) {
            $schema->create('financial_recurring_expense_versions', static function (Blueprint $table): void {
                $table->id();
                $table->foreignId('financial_recurring_expense_id');
                $table->date('effective_from');
                $table->string('label', 160);
                $table->decimal('amount', 14, 2);
                $table->unsignedTinyInteger('due_day');
                $table->text('note')->nullable();
                $table->timestamps();
                $table->unique(['financial_recurring_expense_id', 'effective_from'], 'financial_recurring_expense_version_unique');
            });
        }

        if (!$schema->hasForeignKey('financial_recurring_expense_versions', ['financial_recurring_expense_id'])) {
            $schema->table('financial_recurring_expense_versions', static function (Blueprint $table): void {
                $table->foreign('financial_recurring_expense_id', 'fin_rec_exp_versions_expense_fk')
                    ->references('id')
                    ->on('financial_recurring_expenses')
                    ->cascadeOnDelete();
            });
        }
    }
};
