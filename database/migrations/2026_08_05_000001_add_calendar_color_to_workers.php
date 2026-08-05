<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('workers', static function (Blueprint $table): void {
            $table->string('calendar_color', 7)->nullable()->after('last_name');
        });
    }
};
