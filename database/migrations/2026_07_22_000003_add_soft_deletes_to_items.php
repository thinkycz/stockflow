<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Preserve items referenced by immutable inventory history while hiding them from active catalog queries.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('items', static function (Blueprint $table): void {
            $table->softDeletes();
        });
    }
};
