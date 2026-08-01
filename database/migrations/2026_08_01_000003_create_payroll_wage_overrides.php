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
        Resolver::resolveSchemaBuilder()->create('payroll_wage_overrides', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_report_id')->constrained('payroll_reports')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('workers')->restrictOnDelete();
            $table->decimal('hours', 8, 2);
            $table->decimal('hourly_rate', 14, 2);
            $table->timestamps();
            $table->unique(['payroll_report_id', 'worker_id']);
        });
    }
};
