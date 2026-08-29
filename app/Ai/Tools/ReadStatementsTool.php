<?php

declare(strict_types=1);

namespace App\Ai\Tools;

final class ReadStatementsTool extends AbstractCatalogReadTool
{
    protected const string TOOL_NAME = 'read_statements';

    protected const string TOOL_DESCRIPTION = 'Read statement periods and version metadata.';

    protected const string RESOURCE = 'statements';

    protected const bool STORE_SCOPED = true;
}
