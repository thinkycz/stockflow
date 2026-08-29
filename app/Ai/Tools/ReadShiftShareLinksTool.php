<?php

declare(strict_types=1);

namespace App\Ai\Tools;

final class ReadShiftShareLinksTool extends AbstractCatalogReadTool
{
    protected const string TOOL_NAME = 'read_shift_share_links';

    protected const string TOOL_DESCRIPTION = 'Read active and revoked shift share links.';

    protected const string RESOURCE = 'shift_share_links';

    protected const bool STORE_SCOPED = true;
}
