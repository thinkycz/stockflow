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
            $table->decimal('bolt_cash', 10, 2)->default(0)->after('bolt');
        });
    }
};
