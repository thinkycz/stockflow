<?php

declare(strict_types=1);

namespace App\Ai\Tools;

final class ReadInventoryCountsTool extends AbstractCatalogReadTool
{
    protected const string TOOL_NAME = 'read_inventory_counts';

    protected const string TOOL_DESCRIPTION = 'Read inventory count drafts and completed sessions.';

    protected const string RESOURCE = 'inventory_counts';

    protected const bool STORE_SCOPED = true;
}
