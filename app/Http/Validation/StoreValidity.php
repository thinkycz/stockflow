<?php

declare(strict_types=1);

namespace App\Http\Validation;

use App\Enums\StoreStatusEnum;
use Thinkycz\LaravelCore\Validation\Validity;

class StoreValidity extends AppValidity
{
    /**
     * Name validation rules.
     */
    public function name(): Validity
    {
        return $this->baseValidity->make()->varchar(120);
    }

    /**
     * Address validation rules.
     */
    public function address(): Validity
    {
        return $this->baseValidity->make()->text();
    }

    /**
     * Status validation rules.
     */
    public function status(): Validity
    {
        return $this->baseValidity->make()->inString(StoreStatusEnum::values());
    }

    /**
     * Notes validation rules.
     */
    public function notes(): Validity
    {
        return $this->baseValidity->make()->text();
    }

    /**
     * Warehouse flag validation rules.
     */
    public function isWarehouse(): Validity
    {
        return $this->baseValidity->make()->boolean();
    }

    /**
     * Id validation rules.
     */
    public function id(): Validity
    {
        return $this->baseValidity->id()->exists('stores', 'id', ['user_id', (string) $this->userId]);
    }

    /**
     * Retail store id validation rules (non-warehouse).
     */
    public function retailId(): Validity
    {
        return $this->baseValidity->id()->exists('stores', 'id', [
            'user_id',
            (string) $this->userId,
            'is_warehouse',
            '0',
        ]);
    }

    /**
     * Search validation rules.
     */
    public function search(): Validity
    {
        return $this->baseValidity->search();
    }
}
