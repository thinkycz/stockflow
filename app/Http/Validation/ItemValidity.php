<?php

declare(strict_types=1);

namespace App\Http\Validation;

use Thinkycz\LaravelCore\Validation\Validity;

class ItemValidity extends AppValidity
{
    /**
     * Title validation rules.
     */
    public function title(): Validity
    {
        return $this->baseValidity->make()->varchar(255);
    }

    /**
     * SKU validation rules.
     */
    public function sku(int|null $ignoreId = null): Validity
    {
        $rule = $this->baseValidity->make()->varchar(64);
        $wheres = ['user_id', (string) $this->userId];

        if ($ignoreId === null) {
            $rule = $rule->unique('items', 'sku', null, null, $wheres);
        } else {
            $rule = $rule->unique('items', 'sku', $ignoreId, 'id', $wheres);
        }

        return $rule;
    }

    /**
     * Unit validation rules.
     */
    public function unit(): Validity
    {
        return $this->baseValidity->make()->varchar(16);
    }

    /**
     * Purchase price validation rules.
     */
    public function purchasePrice(): Validity
    {
        return $this->baseValidity->make()->numeric(999999, 0)->addRule('decimal', [0, 2]);
    }

    /**
     * Description validation rules.
     */
    public function description(): Validity
    {
        return $this->baseValidity->make()->text();
    }

    /**
     * Id validation rules.
     */
    public function id(): Validity
    {
        return $this->baseValidity->id()->exists('items', 'id', ['user_id', (string) $this->userId]);
    }

    /**
     * Search validation rules.
     */
    public function search(): Validity
    {
        return $this->baseValidity->search();
    }
}
