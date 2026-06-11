<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AdjustmentReasonEnum;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Services\StockMovementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StockMovementSeeder extends Seeder
{
    /**
     * Seed a baseline of stock movements and adjust store quantities accordingly.
     */
    public function run(): void
    {
        if (StockMovement::query()->exists()) {
            return;
        }

        $user = User::query()->first();
        $items = Item::query()->get()->keyBy('sku');
        $stores = Store::query()->get()->keyBy('name');
        $warehouse = Store::query()->where('is_warehouse', true)->first();

        if (
            !$user instanceof User ||
            $items->isEmpty() ||
            $stores->isEmpty() ||
            !$warehouse instanceof Store
        ) {
            return;
        }

        $service = \app(StockMovementService::class);
        $today = Carbon::now()->startOfDay();

        DB::transaction(function () use ($service, $user, $items, $stores, $warehouse, $today): void {
            $service->createMovement([
                'store_id' => $warehouse->getKey(),
                'note' => 'Počáteční naskladnění.',
                'items' => $items->map(static fn(Item $item): array => [
                    'item_id' => $item->getKey(),
                    'quantity' => 50.0,
                ])->values()->all(),
            ], $user);

            $storePlan = [
                'Praha centrála' => $today->copy()->subDays(7),
                'Brno pobočka' => $today->copy()->subDays(3),
                'Ostrava depo' => $today->copy()->subDays(1),
            ];

            foreach ($storePlan as $storeName => $date) {
                $store = $stores->get($storeName);
                if (!$store instanceof Store) {
                    continue;
                }

                $service->createMovement([
                    'source_store_id' => $warehouse->getKey(),
                    'store_id' => $store->getKey(),
                    'note' => 'Týdenní závoz.',
                    'items' => $items->take(3)->map(static fn(Item $item): array => [
                        'item_id' => $item->getKey(),
                        'quantity' => 5.0,
                    ])->values()->all(),
                ], $user);
            }

            $damaged = $items->get('BUB-CUP-004');
            if ($damaged instanceof Item) {
                $service->createMovement([
                    'mode' => 'adjustment',
                    'store_id' => $warehouse->getKey(),
                    'note' => 'Poškozené kelímky při rozvozu.',
                    'items' => [[
                        'item_id' => $damaged->getKey(),
                        'quantity_after' => 40.0,
                        'adjustment_reason' => AdjustmentReasonEnum::DAMAGED->value,
                    ]],
                ], $user);
            }

            $correction = $items->get('BUB-STR-003');
            if ($correction instanceof Item) {
                $service->createMovement([
                    'mode' => 'adjustment',
                    'store_id' => $warehouse->getKey(),
                    'note' => 'Inventurní rozdíl.',
                    'items' => [[
                        'item_id' => $correction->getKey(),
                        'quantity_after' => 30.0,
                        'adjustment_reason' => AdjustmentReasonEnum::INVENTORY_CORRECTION->value,
                    ]],
                ], $user);
            }
        });
    }
}
