<?php

declare(strict_types=1);

namespace App\Http\Validation;

use Thinkycz\LaravelCore\Validation\BaseValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class ShiftShareLinkValidity
{
    /**
     * Shared validation rule builder.
     */
    public BaseValidity $baseValidity;

    /**
     * Constructor.
     */
    public function __construct(private readonly int $storeId)
    {
        $this->baseValidity = new BaseValidity();
    }

    /**
     * Build validation rules for one store.
     */
    public static function inject(int $storeId): self
    {
        return new self($storeId);
    }

    /**
     * Store-unique link name rules.
     */
    public function name(): Validity
    {
        return $this->baseValidity->make()
            ->string(100)
            ->unique('shift_share_links', 'name', null, 'id', ['store_id', (string) $this->storeId]);
    }
}
