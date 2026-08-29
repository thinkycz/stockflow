<?php

declare(strict_types=1);

namespace App\Ai\Tools;

final class ReadUsersTool extends AbstractCatalogReadTool
{
    protected const string TOOL_NAME = 'read_users';

    protected const string TOOL_DESCRIPTION = 'Read managed limited users without credentials.';

    protected const string RESOURCE = 'users';

    protected const bool SEARCHABLE = true;
}
