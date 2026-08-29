<?php

declare(strict_types=1);

namespace App\Ai\Tools;

final class ReadShiftRequestsTool extends AbstractCatalogReadTool
{
    protected const string TOOL_NAME = 'read_shift_requests';

    protected const string TOOL_DESCRIPTION = 'Read shift requests and month locks.';

    protected const string RESOURCE = 'shift_requests';

    protected const bool STORE_SCOPED = true;
}
