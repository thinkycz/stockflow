<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Inventory\StockMovementService;
use App\Enums\StockMovementClassificationEnum;
use App\Enums\StockMovementOriginEnum;
use App\Enums\StockMovementTypeEnum;
use App\Models\InventorySession;
use App\Models\InventorySessionItem;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Typer;

class BackfillInventoryConsumptionCommand extends Command
{
    /**
     * Console command signature.
     *
     * @var string
     */
    protected $signature = 'stockflow:backfill-inventory-consumption
        {--dry-run : Report changes without writing them}
        {--chunk=200 : Number of inventory sessions processed per batch}
        {--after=0 : Resume after this inventory session id}';

    /**
     * Console command description.
     *
     * @var string
     */
    protected $description = 'Backfill safe inventory consumption intervals into the stock ledger.';

    /**
     * Execute the command.
     */
    public function handle(StockMovementService $movementService): int
    {
        $dryRun = $this->option('dry-run');
        $stats = [
            'intervals' => 0,
            'consumption' => 0,
            'corrections' => 0,
            'unchanged' => 0,
            'skipped' => 0,
        ];
        $chunk = \max(1, Typer::parseInt($this->option('chunk')));
        $after = \max(0, Typer::parseInt($this->option('after')));
        $lastProcessed = $after;

        InventorySession::query()
            ->where('status', 'closed')
            ->where('id', '>', $after)
            ->with(['store', 'items.item'])
            ->orderBy('id')
            ->chunkById($chunk, function ($sessions) use (
                $dryRun,
                $movementService,
                &$stats,
                &$lastProcessed,
            ): void {
                foreach ($sessions as $session) {
                    $lastProcessed = $session->getKey();
                    $this->processSession($session, $dryRun, $movementService, $stats);
                }

                $this->line("Checkpoint: --after={$lastProcessed}");
            });

        $this->table(
            ['Mode', 'Intervals', 'Consumed units', 'Correction units', 'Zero differences', 'Skipped'],
            [[
                $dryRun ? 'dry-run' : 'write',
                $stats['intervals'],
                $stats['consumption'],
                $stats['corrections'],
                $stats['unchanged'],
                $stats['skipped'],
            ]],
        );

        return self::SUCCESS;
    }

    /**
     * Process one inventory in its own bounded transaction.
     *
     * @param array{intervals: int, consumption: float|int, corrections: float|int, unchanged: int, skipped: int} $stats
     */
    private function processSession(
        InventorySession $session,
        bool $dryRun,
        StockMovementService $movementService,
        array &$stats,
    ): void {
        if (StockMovement::query()->where('inventory_session_id', $session->getKey())->exists()) {
            $stats['skipped'] += $session->getItems()->count();

            return;
        }

        /** @var array<int, array{session_item: InventorySessionItem, item: Item, expected: string, counted: string, difference: string, classification: StockMovementClassificationEnum, observation_started_at: Carbon|null}> $movementRows */
        $movementRows = [];
        /** @var array<int, array{expected_quantity: string, quantity_difference: string, classification: string|null, observation_started_at: Carbon}> $updates */
        $updates = [];
        $store = $session->getStore();

        foreach ($session->getItems()->sortBy(static fn(InventorySessionItem $row): int => $row->getItemId()) as $row) {
            if ($row->getExpectedQuantity() !== null) {
                ++$stats['skipped'];

                continue;
            }

            $previous = InventorySessionItem::query()
                ->with('session')
                ->where('item_id', $row->getItemId())
                ->whereHas('session', static function ($query) use ($store, $session): void {
                    $query->where('store_id', $store->getKey())
                        ->where('status', 'closed')
                        ->where('counted_at', '<', $session->getCountedAt()->toDateTimeString());
                })
                ->orderByDesc(
                    InventorySession::query()
                        ->select('counted_at')
                        ->whereColumn('inventory_sessions.id', 'inventory_session_items.session_id')
                        ->limit(1),
                )
                ->first();

            if (!$previous instanceof InventorySessionItem) {
                ++$stats['skipped'];

                continue;
            }

            $previousSession = $previous->getSession();
            $item = $row->getItem();
            $expected = $this->decimal($previous->getQuantity())->plus($this->movementDelta(
                $store,
                $item,
                $previousSession->getCountedAt(),
                $session->getCountedAt(),
            ));
            $counted = $this->decimal($row->getQuantity());
            $difference = $counted->minus($expected);
            $classification = $difference->isNegative()
                ? StockMovementClassificationEnum::CONSUMPTION
                : StockMovementClassificationEnum::INVENTORY_CORRECTION;

            ++$stats['intervals'];
            if ($difference->isNegative()) {
                $stats['consumption'] += (float) (string) $difference->abs();
            } elseif ($difference->isPositive()) {
                $stats['corrections'] += (float) (string) $difference;
            } else {
                ++$stats['unchanged'];
            }

            $updates[$row->getKey()] = [
                'expected_quantity' => (string) $expected,
                'quantity_difference' => (string) $difference,
                'classification' => $difference->isZero() ? null : $classification->value,
                'observation_started_at' => $previousSession->getCountedAt(),
            ];

            if (!$difference->isZero()) {
                $movementRows[] = [
                    'session_item' => $row,
                    'item' => $item,
                    'expected' => (string) $expected,
                    'counted' => (string) $counted,
                    'difference' => (string) $difference,
                    'classification' => $classification,
                    'observation_started_at' => $previousSession->getCountedAt(),
                ];
            }
        }

        if ($dryRun || $updates === []) {
            return;
        }

        DB::transaction(function () use ($session, $updates, $movementRows, $movementService): void {
            if (StockMovement::query()->where('inventory_session_id', $session->getKey())->lockForUpdate()->exists()) {
                return;
            }
            foreach ($updates as $rowId => $attributes) {
                InventorySessionItem::query()->whereKey($rowId)->update($attributes);
            }
            $owner = User::query()->whereKey($session->getUserId())->firstOrFail();
            $movementService->createInventoryReconciliation(
                $session,
                $owner,
                $movementRows,
                StockMovementOriginEnum::MIGRATION,
            );
        }, 3);
    }

    /**
     * Net known ledger change at one store between two physical counts.
     */
    private function movementDelta(Store $store, Item $item, Carbon $from, Carbon $to): BigDecimal
    {
        $rows = DB::table('stock_movement_items')
            ->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_items.stock_movement_id')
            ->where('stock_movements.user_id', $store->getUserId())
            ->where('stock_movement_items.item_id', $item->getKey())
            ->where('stock_movements.occurred_at', '>', $from->toDateTimeString())
            ->where('stock_movements.occurred_at', '<=', $to->toDateTimeString())
            ->where('stock_movements.type', '!=', StockMovementTypeEnum::INVENTORY_RECONCILIATION->value)
            ->get([
                'stock_movements.type',
                'stock_movements.store_id',
                'stock_movements.source_store_id',
                'stock_movement_items.quantity',
                'stock_movement_items.quantity_difference',
            ]);

        $delta = BigDecimal::zero();
        foreach ($rows as $row) {
            $type = StockMovementTypeEnum::from(Typer::assertString($row->type));
            $storeId = Typer::parseNullableInt($row->store_id);
            $sourceStoreId = Typer::parseNullableInt($row->source_store_id);
            $quantity = $this->decimal($row->quantity);

            if ($type === StockMovementTypeEnum::TRANSFER) {
                if ($storeId === $store->getKey()) {
                    $delta = $delta->plus($quantity);
                }
                if ($sourceStoreId === $store->getKey()) {
                    $delta = $delta->minus($quantity);
                }

                continue;
            }

            if ($storeId === $store->getKey()) {
                $delta = $delta->plus($this->decimal($row->quantity_difference));
            }
        }

        return $delta;
    }

    /**
     * Parse a quantity without binary floating-point arithmetic.
     */
    private function decimal(mixed $value): BigDecimal
    {
        return BigDecimal::of((string) Typer::assertScalar($value))->toScale(3, RoundingMode::Unnecessary);
    }
}
