<?php

declare(strict_types=1);

namespace App\Http\Validation;

use Thinkycz\LaravelCore\Validation\BaseValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class NoticeboardConfirmationValidity
{
    /**
     * Base validity.
     */
    public BaseValidity $baseValidity;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->baseValidity = new BaseValidity();
    }

    /**
     * Ordered or unordered collection of selected snapshot ids.
     */
    public function itemIds(): Validity
    {
        return $this->baseValidity->make()->array(null)->min(1);
    }

    /**
     * One selected snapshot id.
     */
    public function itemId(): Validity
    {
        return $this->baseValidity->make()->integer(null, 1);
    }
}
