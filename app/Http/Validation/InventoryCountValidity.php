<?php

declare(strict_types=1);

namespace App\Http\Validation;

use Thinkycz\LaravelCore\Validation\Validity;

class InventoryCountValidity extends AppValidity
{
    /**
     * Store id validation rules (any owned store).
     */
    public function storeId(): Validity
    {
        return $this->baseValidity->id()->exists('stores', 'id', ['user_id', (string) $this->userId]);
    }

    /**
     * Item id validation rules (any owned item).
     */
    public function itemId(): Validity
    {
        return $this->baseValidity->id()->exists('items', 'id', ['user_id', (string) $this->userId]);
    }

    /**
     * Inventory session id validation rules.
     */
    public function id(): Validity
    {
        return $this->baseValidity->id()->exists('inventory_sessions', 'id', ['user_id', (string) $this->userId]);
    }

    /**
     * Rows array validation rules.
     */
    public function rows(): Validity
    {
        return $this->baseValidity->make()->array(null)->min(1);
    }

    /**
     * Per-row quantity validation rules (integer, 0..999999).
     */
    public function rowQuantity(): Validity
    {
        return $this->baseValidity->make()->integer(999999, 0);
    }

    /**
     * Per-row note validation rules (optional text).
     */
    public function rowNote(): Validity
    {
        return $this->baseValidity->make()->text();
    }
}
