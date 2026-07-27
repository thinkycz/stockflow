<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Allow an open inventory draft to exist without a finalized inventory date.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('inventory_sessions', static function (Blueprint $table): void {
            $table->timestamp('counted_at')->nullable()->change();
        });
    }
};
