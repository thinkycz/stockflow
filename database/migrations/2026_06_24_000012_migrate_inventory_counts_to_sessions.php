<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Thinkycz\LaravelCore\Support\Typer;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Groups existing `inventory_counts` rows by
     * `(user_id, store_id, counted_at)` and creates one
     * `inventory_sessions` row per group, plus the matching
     * `inventory_session_items` rows.
     */
    public function up(): void
    {
        $sourceRows = DB::table('inventory_counts')
            ->select('user_id', 'store_id', 'created_by', 'counted_at', 'created_at', 'updated_at')
            ->get();

        $grouped = [];

        foreach ($sourceRows as $row) {
            $key = Typer::assertInt($row->user_id)
                . '|'
                . Typer::assertInt($row->store_id)
                . '|'
                . Typer::assertString($row->counted_at);

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'user_id' => Typer::assertInt($row->user_id),
                    'store_id' => Typer::assertInt($row->store_id),
                    'created_by' => $row->created_by === null
                        ? null
                        : Typer::assertInt($row->created_by),
                    'counted_at' => Typer::assertString($row->counted_at),
                    'note' => null,
                    'created_at' => Typer::assertString($row->created_at),
                    'updated_at' => Typer::assertString($row->updated_at),
                ];
            }
        }

        if ($grouped === []) {
            Log::info('inventory_counts: nothing to migrate');

            return;
        }

        DB::transaction(static function () use ($grouped): void {
            DB::table('inventory_sessions')->insert(\array_values($grouped));

            $sessionMap = DB::table('inventory_sessions')
                ->select('id', 'user_id', 'store_id', 'counted_at')
                ->get();

            $sessionIndex = [];

            foreach ($sessionMap as $row) {
                $key = Typer::assertInt($row->user_id)
                    . '|'
                    . Typer::assertInt($row->store_id)
                    . '|'
                    . Typer::assertString($row->counted_at);
                $sessionIndex[$key] = Typer::assertInt($row->id);
            }

            $itemRows = DB::table('inventory_counts')
                ->select('user_id', 'store_id', 'counted_at', 'item_id', 'quantity', 'note', 'created_at', 'updated_at')
                ->get()
                ->map(static function (object $row) use ($sessionIndex): array {
                    $key = Typer::assertInt($row->user_id)
                        . '|'
                        . Typer::assertInt($row->store_id)
                        . '|'
                        . Typer::assertString($row->counted_at);

                    if (!isset($sessionIndex[$key])) {
                        throw new RuntimeException("Missing inventory_sessions row for {$key}");
                    }

                    return [
                        'session_id' => $sessionIndex[$key],
                        'item_id' => Typer::assertInt($row->item_id),
                        'quantity' => Typer::assertInt($row->quantity),
                        'note' => $row->note,
                        'created_at' => Typer::assertString($row->created_at),
                        'updated_at' => Typer::assertString($row->updated_at),
                    ];
                })
                ->all();

            // Chunk inserts to keep the statement size under MySQL limits.
            foreach (\array_chunk($itemRows, 500) as $chunk) {
                DB::table('inventory_session_items')->insert($chunk);
            }
        });
    }
};
