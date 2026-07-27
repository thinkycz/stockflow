<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

return new class extends Migration {
    public function up(): void
    {
        $rows = DB::table('stock_movement_sequences')
            ->selectRaw('type, year, MIN(user_id) as user_id, MAX(last_number) as last_number')
            ->groupBy('type', 'year')
            ->get();

        DB::table('stock_movement_sequences')->delete();
        foreach ($rows as $row) {
            DB::table('stock_movement_sequences')->insert([
                'user_id' => Typer::parseInt($row->user_id),
                'type' => Typer::assertString($row->type),
                'year' => Typer::parseInt($row->year),
                'last_number' => Typer::parseInt($row->last_number),
            ]);
        }

        Resolver::resolveSchemaBuilder()->table('stock_movement_sequences', static function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->unique(['type', 'year']);
        });
    }
};
