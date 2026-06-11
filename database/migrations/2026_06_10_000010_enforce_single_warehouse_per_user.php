<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Before this migration, two concurrent callers of
     * `User::provisionWarehouse()` could both see "no warehouse" and
     * both create a Store, with one of them hitting the
     * `(user_id, name)` unique constraint. The new partial unique
     * index `(user_id) WHERE is_warehouse = 1` (MySQL 8) makes the
     * invariant explicit at the database layer.
     *
     * The SQLite test driver does not support partial unique indexes,
     * so the application-level `updateOrCreate` + try/catch fallback
     * in `User::provisionWarehouse()` covers that environment. A
     * separate, broader unique index on `user_id` would conflict with
     * the per-user multi-store model, so we cannot fall back to a
     * plain unique index in SQLite.
     */
    public function up(): void
    {
        $schema = Resolver::resolveSchemaBuilder();
        $driver = $schema->getConnection()->getDriverName();

        $this->dedupeExistingWarehouses();

        if ($driver !== 'mysql') {
            return;
        }

        $schema->table('stores', static function (Blueprint $table): void {
            $table->unique('user_id', 'stores_user_id_warehouse_unique');
        });

        DB::statement(
            'ALTER TABLE `stores` DROP INDEX `stores_user_id_warehouse_unique`, ' .
            'ADD UNIQUE INDEX `stores_user_id_warehouse_unique` (`user_id`) ' .
            'WHERE `is_warehouse` = 1',
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Resolver::resolveSchemaBuilder();
        $driver = $schema->getConnection()->getDriverName();

        if ($driver !== 'mysql') {
            return;
        }

        $schema->table('stores', static function (Blueprint $table): void {
            $table->dropUnique('stores_user_id_warehouse_unique');
        });
    }

    /**
     * Collapse any pre-existing duplicate warehouses per user to a
     * single row, so the new unique index can be created.
     */
    private function dedupeExistingWarehouses(): void
    {
        /** @var array<int, object{user_id: int, keep_id: int}> $duplicates */
        $duplicates = DB::select(
            'SELECT user_id, MIN(id) AS keep_id FROM stores WHERE is_warehouse = 1 GROUP BY user_id HAVING COUNT(*) > 1',
        );

        foreach ($duplicates as $row) {
            $rowValues = (array) $row;

            DB::table('stores')
                ->where('user_id', Typer::assertInt($rowValues['user_id'] ?? null))
                ->where('is_warehouse', true)
                ->where('id', '!=', Typer::assertInt($rowValues['keep_id'] ?? null))
                ->update(['is_warehouse' => false, 'name' => 'Warehouse (legacy)']);
        }
    }
};
