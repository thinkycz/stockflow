<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Mirror the `is_warehouse` flag into the `warehouse_owner_id`
 * unique-key column. Centralising the assignment in a trait keeps
 * the data invariant safe regardless of which controller, factory,
 * or seeder path creates a Store.
 *
 * @mixin Model
 */
trait HasWarehouseOwner
{
    /**
     * Boot the trait.
     */
    public static function bootHasWarehouseOwner(): void
    {
        static::creating(static function (Model $model): void {
            self::syncWarehouseOwner($model);
        });

        static::updating(static function (Model $model): void {
            self::syncWarehouseOwner($model);
        });
    }

    /**
     * Mirror the `is_warehouse` flag into the unique `warehouse_owner_id` column.
     */
    private static function syncWarehouseOwner(Model $model): void
    {
        $isWarehouse = (bool) $model->getAttribute('is_warehouse');
        $ownerId = $model->getAttribute('user_id');
        $model->setAttribute('warehouse_owner_id', $isWarehouse && $ownerId !== null ? $ownerId : null);
    }
}
