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
        Resolver::resolveSchemaBuilder()->table('statement_days', static function (Blueprint $table): void {
            $table->boolean('cash_checked')->default(false)->after('total');
        });

        Resolver::resolveSchemaBuilder()->table('statement_version_days', static function (Blueprint $table): void {
            $table->boolean('cash_checked')->default(false)->after('total');
        });
    }
};
