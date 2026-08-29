<?php

declare(strict_types=1);

namespace App\Ai\Tools;

final class ReadRecurringExpensesTool extends AbstractCatalogReadTool
{
    protected const string TOOL_NAME = 'read_recurring_expenses';

    protected const string TOOL_DESCRIPTION = 'Read recurring expense versions and effective periods.';

    protected const string RESOURCE = 'recurring_expenses';

    protected const bool SEARCHABLE = true;

    protected const bool STORE_SCOPED = true;
}
