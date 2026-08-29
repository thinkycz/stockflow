<?php

declare(strict_types=1);

namespace App\Ai\Tools;

final class ReadStockMovementsTool extends AbstractCatalogReadTool
{
    protected const string TOOL_NAME = 'read_stock_movements';

    protected const string TOOL_DESCRIPTION = 'Read stock movements and reversals.';

    protected const string RESOURCE = 'stock_movements';

    protected const bool SEARCHABLE = true;

    protected const bool STORE_SCOPED = true;
}
