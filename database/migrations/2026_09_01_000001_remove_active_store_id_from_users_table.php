<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Remove the account-wide active store preference.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('users', static function (Blueprint $table): void {
            $table->dropForeign(['active_store_id']);
            $table->dropIndex(['active_store_id']);
            $table->dropColumn('active_store_id');
        });
    }
};
