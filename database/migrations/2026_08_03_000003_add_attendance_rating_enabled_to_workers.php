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
        Resolver::resolveSchemaBuilder()->table('workers', static function (Blueprint $table): void {
            $table->boolean('attendance_rating_enabled')->default(true)->after('hourly_rate');
        });
    }
};
