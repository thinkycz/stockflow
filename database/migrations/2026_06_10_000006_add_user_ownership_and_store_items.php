<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $schema = Resolver::resolveSchemaBuilder();

        $schema->table('stores', static function (Blueprint $table): void {
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->boolean('is_warehouse')->default(false)->after('status');

            $table->index(['user_id', 'is_warehouse']);
        });

        $schema->table('items', static function (Blueprint $table): void {
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->index('user_id');
        });

        $schema->table('stock_movements', static function (Blueprint $table): void {
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->index('user_id');
        });

        $defaultUserId = DB::table('users')->orderBy('id')->value('id');

        if ($defaultUserId !== null) {
            DB::table('stores')->whereNull('user_id')->update(['user_id' => $defaultUserId]);
            DB::table('items')->whereNull('user_id')->update(['user_id' => $defaultUserId]);
            DB::table('stock_movements')->whereNull('user_id')->update([
                'user_id' => DB::raw('COALESCE(created_by, ' . (int) $defaultUserId . ')'),
            ]);
        }

        $warehouseId = DB::table('stores')
            ->where('user_id', $defaultUserId)
            ->where('is_warehouse', false)
            ->orderBy('id')
            ->value('id');

        if ($warehouseId !== null) {
            DB::table('stores')->where('id', $warehouseId)->update(['is_warehouse' => true]);
        } elseif ($defaultUserId !== null) {
            DB::table('stores')->insert([
                'user_id' => $defaultUserId,
                'name' => 'Warehouse',
                'address' => null,
                'status' => 'active',
                'is_warehouse' => true,
                'notes' => null,
                'created_at' => \now(),
                'updated_at' => \now(),
            ]);
            $warehouseId = DB::table('stores')
                ->where('user_id', $defaultUserId)
                ->where('is_warehouse', true)
                ->value('id');
        }

        $schema->create('store_items', static function (Blueprint $table): void {
            $table->id();

            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();

            $table->foreignId('item_id')
                ->constrained('items')
                ->restrictOnDelete();

            $table->decimal('quantity', 12, 3)->default(0);

            $table->unique(['store_id', 'item_id']);
            $table->index('item_id');
        });

        if ($warehouseId !== null) {
            $items = DB::table('items')->select(['id', 'current_quantity'])->get();

            foreach ($items as $item) {
                DB::table('store_items')->insert([
                    'store_id' => $warehouseId,
                    'item_id' => $item->id,
                    'quantity' => $item->current_quantity ?? 0,
                ]);
            }
        }

        $schema->table('items', static function (Blueprint $table): void {
            $table->dropUnique(['sku']);
            $table->dropColumn('current_quantity');
            $table->unique(['user_id', 'sku']);
        });

        $schema->table('stock_movements', static function (Blueprint $table): void {
            $table->dropUnique(['number']);
            $table->unique(['user_id', 'number']);
        });

        $schema->table('stores', static function (Blueprint $table): void {
            $table->unique(['user_id', 'name']);
        });

        $this->migrateStockMovementSequences($defaultUserId);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Resolver::resolveSchemaBuilder();

        $schema->dropIfExists('store_items');

        $schema->table('items', static function (Blueprint $table): void {
            $table->decimal('current_quantity', 12, 3)->default(0)->after('unit');
            $table->dropUnique(['user_id', 'sku']);
            $table->unique('sku');
            $table->dropConstrainedForeignId('user_id');
        });

        $schema->table('stores', static function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'name']);
            $table->dropColumn('is_warehouse');
            $table->dropConstrainedForeignId('user_id');
        });

        $schema->table('stock_movements', static function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'number']);
            $table->unique('number');
            $table->dropConstrainedForeignId('user_id');
        });

        $schema->dropIfExists('stock_movement_sequences');

        $schema->create('stock_movement_sequences', static function (Blueprint $table): void {
            $table->string('type');
            $table->smallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->primary(['type', 'year']);
        });
    }

    /**
     * Rebuild stock_movement_sequences with user_id in the primary key.
     */
    private function migrateStockMovementSequences(int|string|null $defaultUserId): void
    {
        $schema = Resolver::resolveSchemaBuilder();
        $rows = DB::table('stock_movement_sequences')->get();

        $schema->dropIfExists('stock_movement_sequences');

        $schema->create('stock_movement_sequences', static function (Blueprint $table): void {
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('type');

            $table->smallInteger('year');

            $table->unsignedInteger('last_number')->default(0);

            $table->primary(['user_id', 'type', 'year']);
        });

        if ($defaultUserId === null) {
            return;
        }

        foreach ($rows as $row) {
            DB::table('stock_movement_sequences')->insert([
                'user_id' => $defaultUserId,
                'type' => $row->type,
                'year' => $row->year,
                'last_number' => $row->last_number,
            ]);
        }
    }
};
