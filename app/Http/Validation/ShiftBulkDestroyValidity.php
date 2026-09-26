<?php

declare(strict_types=1);

namespace App\Http\Validation;

use Thinkycz\LaravelCore\Validation\BaseValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class ShiftBulkDestroyValidity
{
    /**
     * Base validation rules.
     */
    public BaseValidity $baseValidity;

    /**
     * Create bulk selection validation.
     */
    public function __construct()
    {
        $this->baseValidity = new BaseValidity();
    }

    /**
     * Validate an explicit store identifier.
     */
    public function storeId(): Validity
    {
        return $this->baseValidity->id();
    }

    /**
     * Validate a nonempty selection.
     */
    public function shiftIds(): Validity
    {
        return $this->baseValidity->make()->array(null)->min(1);
    }

    /**
     * Validate a distinct positive shift identifier.
     */
    public function shiftId(): Validity
    {
        return $this->baseValidity->id()->distinct();
    }
}
