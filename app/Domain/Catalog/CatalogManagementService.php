<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Models\InventorySessionItem;
use App\Models\Item;
use App\Models\StockMovementItem;
use App\Models\StoreItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Thrower;

class CatalogManagementService
{
    /**
     * Create an item and its zero-quantity warehouse row transactionally.
     */
    public function createItem(
        User $actor,
        string $title,
        string|null $sku,
        string|null $unit,
        string $purchasePrice,
        string|null $description,
    ): Item {
        $this->assertAdmin($actor);
        $warehouseId = $actor->warehouse()->getKey();

        return DB::transaction(static function () use ($actor, $title, $sku, $unit, $purchasePrice, $description, $warehouseId): Item {
            $item = Item::query()->create([
                'user_id' => $actor->getKey(),
                'title' => $title,
                'sku' => $sku,
                'unit' => $unit,
                'purchase_price' => $purchasePrice,
                'description' => $description,
            ]);
            StoreItem::query()->create([
                'store_id' => $warehouseId,
                'item_id' => $item->getKey(),
                'quantity' => 0,
            ]);

            return $item;
        });
    }

    /**
     * Update an owned item without changing stock quantities.
     */
    public function updateItem(
        User $actor,
        Item $item,
        string $title,
        string|null $sku,
        string|null $unit,
        string $purchasePrice,
        string|null $description,
    ): Item {
        $this->authorizeItem($actor, $item);
        $item->update([
            'title' => $title,
            'sku' => $sku,
            'unit' => $unit,
            'purchase_price' => $purchasePrice,
            'description' => $description,
        ]);

        return $item->refresh();
    }

    /**
     * Delete an unreferenced item and its draft/session rows.
     */
    public function deleteItem(User $actor, Item $item): void
    {
        $this->authorizeItem($actor, $item);
        $hasMovements = StockMovementItem::query()
            ->whereHas('stockMovement', static function (Builder $query) use ($item): void {
                $query->where('user_id', $item->getUserId());
            })
            ->where('item_id', $item->getKey())
            ->exists();

        if ($hasMovements) {
            Thrower::default()->message('item', \__('Cannot delete an item that has stock movement history.'))->throw();
        }

        DB::transaction(static function () use ($item): void {
            InventorySessionItem::query()
                ->where('item_id', $item->getKey())
                ->whereHas('session', static function (Builder $query): void {
                    $query->where('status', 'draft');
                })
                ->delete();
            $item->storeItems()->delete();
            $item->delete();
        });
    }

    /**
     * Ensure an item belongs to the main administrator.
     */
    private function authorizeItem(User $actor, Item $item): void
    {
        $this->assertAdmin($actor);

        if ($item->getUserId() !== $actor->getKey()) {
            \abort(404);
        }
    }

    /**
     * Ensure the assistant actor is the main administrator.
     */
    private function assertAdmin(User $actor): void
    {
        if (!$actor->isAdmin()) {
            \abort(403);
        }
    }
}
