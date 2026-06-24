<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->dropIfExists('inventory_counts');
    }
};
