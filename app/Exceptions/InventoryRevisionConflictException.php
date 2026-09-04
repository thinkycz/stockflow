<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\InventorySessionItem;
use RuntimeException;

final class InventoryRevisionConflictException extends RuntimeException
{
    /**
     * Preserve the authoritative row for callers resolving the conflict.
     */
    public function __construct(public readonly InventorySessionItem|null $row)
    {
        parent::__construct('Inventory row revision conflict.');
    }
}
