<?php

declare(strict_types=1);

namespace App\Ai\Tools;

final class ReadItemsTool extends AbstractCatalogReadTool
{
    protected const string TOOL_NAME = 'read_items';

    protected const string TOOL_DESCRIPTION = 'Read catalog items and aggregate stock.';

    protected const string RESOURCE = 'items';

    protected const bool SEARCHABLE = true;
}
