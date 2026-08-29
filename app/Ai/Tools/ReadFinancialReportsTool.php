<?php

declare(strict_types=1);

namespace App\Ai\Tools;

final class ReadFinancialReportsTool extends AbstractCatalogReadTool
{
    protected const string TOOL_NAME = 'read_financial_reports';

    protected const string TOOL_DESCRIPTION = 'Read financial report lifecycle and totals.';

    protected const string RESOURCE = 'income_expenses';

    protected const bool STORE_SCOPED = true;
}
