<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Each user can own at most one warehouse store. The naive
     * `UNIQUE (user_id, is_warehouse)` index would also block
     * multiple non-warehouse stores per user, so we materialize a
     * nullable `warehouse_owner_id` column that is non-null only on
     * warehouse rows. The unique index on that column allows any
     * number of `NULL` values (per the SQL standard) while still
     * preventing two `is_warehouse = true` rows for the same user.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('stores', static function (Blueprint $table): void {
            $table->foreignId('warehouse_owner_id')
                ->nullable()
                ->after('is_warehouse')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->unique('warehouse_owner_id');
        });

        DB::table('stores')
            ->where('is_warehouse', true)
            ->update(['warehouse_owner_id' => DB::raw('user_id')]);
    }
};
