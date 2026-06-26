<?php

declare(strict_types=1);

namespace App\Http\Validation;

use App\Models\User;
use Thinkycz\LaravelCore\Validation\BaseValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class InventoryCountValidity
{
    /**
     * Base validity.
     */
    public BaseValidity $baseValidity;

    /**
     * Constructor.
     */
    public function __construct(
        private readonly int|null $userId = null,
    ) {
        $this->baseValidity = new BaseValidity();
    }

    /**
     * Inject.
     */
    public static function inject(int|null $userId = null): self
    {
        return new self($userId ?? User::mustAuth()->getKey());
    }

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
     * Per-row quantity validation rules (integer, 0..999999, optional).
     *
     * A null quantity means "do not touch the existing on-hand quantity
     * for this row" and is filtered out before persistence by the
     * session service. The `nullable` presence flag is applied at the
     * controller level so this method stays purely about type and
     * range.
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
