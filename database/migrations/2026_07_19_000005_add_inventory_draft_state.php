<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('inventory_sessions', static function (Blueprint $table): void {
            $table->string('status', 20)->default('closed')->after('created_by')->index();
            $table->timestamp('started_at')->nullable()->after('status');
            $table->timestamp('closed_at')->nullable()->after('counted_at');
            $table->timestamp('cancelled_at')->nullable()->after('closed_at');
            $table->json('opening_snapshot')->nullable()->after('cancelled_at');
            $table->unsignedBigInteger('active_store_key')->nullable()->after('store_id')->unique();
        });

        DB::table('inventory_sessions')->update([
            'status' => 'closed',
            'started_at' => DB::raw('counted_at'),
            'closed_at' => DB::raw('counted_at'),
        ]);

        Resolver::resolveSchemaBuilder()->table('inventory_session_items', static function (Blueprint $table): void {
            $table->timestamp('counted_at')->nullable()->after('quantity');
            $table->unsignedInteger('opening_quantity')->nullable()->after('counted_at');
            $table->unsignedInteger('client_version')->default(0)->after('opening_quantity');
        });

        DB::statement(
            'UPDATE inventory_session_items SET counted_at = '
            . '(SELECT counted_at FROM inventory_sessions WHERE inventory_sessions.id = inventory_session_items.session_id), '
            . 'opening_quantity = expected_quantity',
        );
    }
};
