<?php

declare(strict_types=1);

namespace App\Ai\Tools;

final class ReadNoticeboardTool extends AbstractCatalogReadTool
{
    protected const string TOOL_NAME = 'read_noticeboard';

    protected const string TOOL_DESCRIPTION = 'Read noticeboard metadata without binary content.';

    protected const string RESOURCE = 'noticeboard';

    protected const bool SEARCHABLE = true;

    protected const bool STORE_SCOPED = true;
}
