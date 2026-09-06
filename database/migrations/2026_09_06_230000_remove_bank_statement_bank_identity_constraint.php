<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Account-aware duplicate checks run under the store lock; account identifiers remain encrypted.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('bank_statements', static function (Blueprint $table): void {
            $table->dropUnique('bank_statements_logical_unique');
        });
    }
};
