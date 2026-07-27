<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('stock_movements', static function (Blueprint $table): void {
            $table->foreignId('reversal_of_id')
                ->nullable()
                ->after('inventory_session_id')
                ->constrained('stock_movements')
                ->restrictOnDelete();
            $table->unique('reversal_of_id');
            $table->text('reversal_reason')->nullable()->after('note');
            $table->timestamp('reversed_at')->nullable()->after('reversal_reason')->index();
        });

        Resolver::resolveSchemaBuilder()->table('stock_movement_items', static function (Blueprint $table): void {
            $table->decimal('unit_cost', 14, 4)->nullable()->after('quantity');
            $table->boolean('unit_cost_estimated')->default(false)->after('unit_cost');
        });

        DB::table('stock_movement_items')->update([
            'unit_cost' => DB::raw('CASE WHEN ABS(COALESCE(quantity_difference, quantity, 0)) > 0 THEN total / ABS(COALESCE(quantity_difference, quantity, 0)) ELSE 0 END'),
            'unit_cost_estimated' => true,
        ]);
    }
};
