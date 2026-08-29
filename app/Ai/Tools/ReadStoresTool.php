<?php

declare(strict_types=1);

namespace App\Ai\Tools;

final class ReadStoresTool extends AbstractCatalogReadTool
{
    protected const string TOOL_NAME = 'read_stores';

    protected const string TOOL_DESCRIPTION = 'Read owned stores and their operational state.';

    protected const string RESOURCE = 'stores';

    protected const bool SEARCHABLE = true;
}
