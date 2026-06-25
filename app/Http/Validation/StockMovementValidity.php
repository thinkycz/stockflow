<?php

declare(strict_types=1);

namespace App\Http\Validation;

use App\Enums\AdjustmentReasonEnum;
use App\Enums\StockMovementTypeEnum;
use Thinkycz\LaravelCore\Validation\Validity;

class StockMovementValidity extends AppValidity
{
    /**
     * Type validation rules.
     */
    public function type(): Validity
    {
        return $this->baseValidity->make()->inString(StockMovementTypeEnum::values());
    }

    /**
     * Store id validation rules (any owned store).
     */
    public function storeId(): Validity
    {
        return $this->baseValidity->id()->exists('stores', 'id', ['user_id', (string) $this->userId]);
    }

    /**
     * Active store id validation rules (owned and active).
     */
    public function activeStoreId(): Validity
    {
        return $this->baseValidity->id()->exists('stores', 'id', [
            'user_id',
            (string) $this->userId,
            'status',
            'active',
        ]);
    }

    /**
     * Retail destination store id validation rules.
     */
    public function retailStoreId(): Validity
    {
        return $this->baseValidity->id()->exists('stores', 'id', [
            'user_id',
            (string) $this->userId,
            'is_warehouse',
            '0',
        ]);
    }

    /**
     * Warehouse store id validation rules.
     */
    public function warehouseStoreId(): Validity
    {
        return $this->baseValidity->id()->exists('stores', 'id', [
            'user_id',
            (string) $this->userId,
            'is_warehouse',
            '1',
        ]);
    }

    /**
     * Note validation rules.
     */
    public function note(): Validity
    {
        return $this->baseValidity->make()->text();
    }

    /**
     * Items array validation rules.
     */
    public function items(): Validity
    {
        return $this->baseValidity->make()->array(null)->min(1);
    }

    /**
     * Per-row item id validation rules.
     */
    public function rowItemId(): Validity
    {
        return $this->baseValidity->id()->exists('items', 'id', ['user_id', (string) $this->userId]);
    }

    /**
     * Per-row quantity validation rules (integer, 1..999999).
     */
    public function rowQuantity(): Validity
    {
        return $this->baseValidity->make()->integer(999999, 1);
    }

    /**
     * Per-row quantity after validation rules (integer, 0..999999).
     */
    public function rowQuantityAfter(): Validity
    {
        return $this->baseValidity->make()->integer(999999, 0);
    }

    /**
     * Per-row adjustment reason validation rules.
     */
    public function rowAdjustmentReason(): Validity
    {
        return $this->baseValidity->make()->inString(AdjustmentReasonEnum::values());
    }

    /**
     * Id validation rules.
     */
    public function id(): Validity
    {
        return $this->baseValidity->id()->exists('stock_movements', 'id', ['user_id', (string) $this->userId]);
    }

    /**
     * Search validation rules.
     */
    public function search(): Validity
    {
        return $this->baseValidity->search();
    }

    /**
     * Type filter validation rules.
     */
    public function typeFilter(): Validity
    {
        return $this->baseValidity->make()->inString(StockMovementTypeEnum::values());
    }

    /**
     * Date range start validation rules.
     */
    public function dateFrom(): Validity
    {
        return $this->baseValidity->date();
    }

    /**
     * Date range end validation rules.
     */
    public function dateTo(): Validity
    {
        return $this->baseValidity->date();
    }
}
