<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\StockMovementClassificationEnum;
use App\Enums\StockMovementOriginEnum;
use App\Enums\StockMovementTypeEnum;
use App\Models\InventorySession;
use App\Models\InventorySessionItem;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Services\StockMovementService;
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
    protected $signature = 'stockflow:backfill-inventory-consumption {--dry-run : Report changes without writing them}';

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
        /** @var array<int, array<int, array{session_item: InventorySessionItem, item: Item, expected: int, counted: int, difference: int, classification: StockMovementClassificationEnum, observation_started_at: Carbon|null}>> $rowsBySession */
        $rowsBySession = [];
        /** @var array<int, array{expected_quantity: int, quantity_difference: int, classification: string|null, observation_started_at: Carbon}> $updates */
        $updates = [];

        $existingSessionIds = StockMovement::query()
            ->where('type', StockMovementTypeEnum::INVENTORY_RECONCILIATION->value)
            ->whereNotNull('inventory_session_id')
            ->pluck('inventory_session_id')
            ->map(static fn(mixed $value): int => Typer::parseInt($value))
            ->all();
        $existingLookup = \array_fill_keys($existingSessionIds, true);

        $items = InventorySessionItem::query()
            ->with(['session.store', 'item'])
            ->get()
            ->sortBy(static function (InventorySessionItem $row): string {
                $session = $row->getSession();

                return \mb_str_pad((string) $session->getStore()->getKey(), 12, '0', \STR_PAD_LEFT)
                    . ':' . \mb_str_pad((string) $row->getItemId(), 12, '0', \STR_PAD_LEFT)
                    . ':' . $session->getCountedAt()->format('YmdHis.u')
                    . ':' . \mb_str_pad((string) $row->getKey(), 12, '0', \STR_PAD_LEFT);
            })
            ->groupBy(static function (InventorySessionItem $row): string {
                return $row->getSession()->getStore()->getKey() . ':' . $row->getItemId();
            });

        foreach ($items as $group) {
            $previous = null;

            foreach ($group as $row) {
                if (!$previous instanceof InventorySessionItem) {
                    $previous = $row;
                    ++$stats['skipped'];

                    continue;
                }

                $session = $row->getSession();
                $sessionId = $session->getKey();
                if (isset($existingLookup[$sessionId]) || $row->getExpectedQuantity() !== null) {
                    $previous = $row;
                    ++$stats['skipped'];

                    continue;
                }

                $previousSession = $previous->getSession();
                $store = $session->getStore();
                $item = $row->getItem();
                $expected = $previous->getQuantity() + $this->movementDelta(
                    $store,
                    $item,
                    $previousSession->getCountedAt(),
                    $session->getCountedAt(),
                );
                $difference = $row->getQuantity() - $expected;
                $classification = $difference < 0
                    ? StockMovementClassificationEnum::CONSUMPTION
                    : StockMovementClassificationEnum::INVENTORY_CORRECTION;

                ++$stats['intervals'];
                if ($difference < 0) {
                    $stats['consumption'] += \abs($difference);
                } elseif ($difference > 0) {
                    $stats['corrections'] += $difference;
                } else {
                    ++$stats['unchanged'];
                }

                $updates[$row->getKey()] = [
                    'expected_quantity' => $expected,
                    'quantity_difference' => $difference,
                    'classification' => $difference === 0 ? null : $classification->value,
                    'observation_started_at' => $previousSession->getCountedAt(),
                ];

                if ($difference !== 0) {
                    $rowsBySession[$sessionId][] = [
                        'session_item' => $row,
                        'item' => $item,
                        'expected' => $expected,
                        'counted' => $row->getQuantity(),
                        'difference' => $difference,
                        'classification' => $classification,
                        'observation_started_at' => $previousSession->getCountedAt(),
                    ];
                }

                $previous = $row;
            }
        }

        if (!$dryRun) {
            DB::transaction(function () use ($updates, $rowsBySession, $movementService): void {
                foreach ($updates as $itemId => $attributes) {
                    InventorySessionItem::query()->whereKey($itemId)->update($attributes);
                }
                foreach ($rowsBySession as $sessionId => $rows) {
                    $session = InventorySession::query()->with('store')->whereKey($sessionId)->first();
                    if (!$session instanceof InventorySession) {
                        continue;
                    }
                    $owner = User::query()->whereKey($session->getUserId())->first();
                    if (!$owner instanceof User) {
                        continue;
                    }
                    $movementService->createInventoryReconciliation(
                        $session,
                        $owner,
                        $rows,
                        StockMovementOriginEnum::MIGRATION,
                    );
                }
            });
        }

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
     * Net known ledger change at one store between two physical counts.
     */
    private function movementDelta(Store $store, Item $item, Carbon $from, Carbon $to): int
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

        $delta = 0;
        foreach ($rows as $row) {
            $type = StockMovementTypeEnum::from(Typer::assertString($row->type));
            $storeId = Typer::parseNullableInt($row->store_id);
            $sourceStoreId = Typer::parseNullableInt($row->source_store_id);
            $quantity = Typer::parseInt($row->quantity);

            if ($type === StockMovementTypeEnum::TRANSFER) {
                if ($storeId === $store->getKey()) {
                    $delta += $quantity;
                }
                if ($sourceStoreId === $store->getKey()) {
                    $delta -= $quantity;
                }

                continue;
            }

            if ($storeId === $store->getKey()) {
                $delta += Typer::parseInt($row->quantity_difference);
            }
        }

        return $delta;
    }
}
