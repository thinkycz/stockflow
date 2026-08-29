<?php

declare(strict_types=1);

namespace App\Ai\Tools;

final class ReadPayrollTool extends AbstractCatalogReadTool
{
    protected const string TOOL_NAME = 'read_payroll';

    protected const string TOOL_DESCRIPTION = 'Read payroll report lifecycle and totals.';

    protected const string RESOURCE = 'payroll';

    protected const bool STORE_SCOPED = true;
}
