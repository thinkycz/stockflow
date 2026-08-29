<?php

declare(strict_types=1);

namespace App\Ai\Tools;

final class ReadAttendanceTool extends AbstractCatalogReadTool
{
    protected const string TOOL_NAME = 'read_attendance';

    protected const string TOOL_DESCRIPTION = 'Read attendance sessions and state.';

    protected const string RESOURCE = 'attendance';

    protected const bool STORE_SCOPED = true;
}
