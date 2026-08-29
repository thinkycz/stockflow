<?php

declare(strict_types=1);

namespace App\Ai\Tools;

final class ReadChecklistsTool extends AbstractCatalogReadTool
{
    protected const string TOOL_NAME = 'read_checklists';

    protected const string TOOL_DESCRIPTION = 'Read checklist days and completion state.';

    protected const string RESOURCE = 'checklists';

    protected const bool STORE_SCOPED = true;
}
