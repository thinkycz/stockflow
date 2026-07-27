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
        Resolver::resolveSchemaBuilder()->table('stock_movements', static function (Blueprint $table): void {
            $table->timestamp('occurred_at')->nullable()->after('type')->index();
            $table->string('origin')->default('manual')->after('occurred_at')->index();
            $table->foreignId('inventory_session_id')
                ->nullable()
                ->after('origin')
                ->constrained('inventory_sessions')
                ->restrictOnDelete();
            $table->unique('inventory_session_id');
        });

        Resolver::resolveSchemaBuilder()->table('inventory_session_items', static function (Blueprint $table): void {
            $table->unsignedInteger('expected_quantity')->nullable()->after('quantity');
            $table->integer('quantity_difference')->nullable()->after('expected_quantity');
            $table->string('classification')->nullable()->after('quantity_difference');
            $table->timestamp('observation_started_at')->nullable()->after('classification');
            $table->unique(['session_id', 'item_id']);
        });

        Resolver::resolveSchemaBuilder()->table('stock_movement_items', static function (Blueprint $table): void {
            $table->string('classification')->nullable()->after('adjustment_reason')->index();
            $table->timestamp('observation_started_at')->nullable()->after('classification');
            $table->foreignId('inventory_session_item_id')
                ->nullable()
                ->after('observation_started_at')
                ->constrained('inventory_session_items')
                ->restrictOnDelete();
            $table->unique('inventory_session_item_id');
        });

        DB::table('stock_movements')->where('type', 'outgoing')->update(['type' => 'transfer']);
        DB::table('stock_movements')->whereNull('occurred_at')->update([
            'occurred_at' => DB::raw('created_at'),
        ]);
        DB::table('stock_movement_items')
            ->whereNotNull('adjustment_reason')
            ->whereNull('classification')
            ->update(['classification' => DB::raw('adjustment_reason')]);
    }
};
