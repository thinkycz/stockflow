<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Add an explicit lifecycle marker without hiding historical relations.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('workers', static function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->after('attendance_rating_enabled')->index();
        });
    }

    /**
     * Remove the lifecycle marker.
     */
    public function down(): void
    {
        Resolver::resolveSchemaBuilder()->table('workers', static function (Blueprint $table): void {
            $table->dropColumn('archived_at');
        });
    }
};
