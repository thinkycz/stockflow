<?php

declare(strict_types=1);

namespace App\Ai\Tools;

final class ReadSettingsTool extends AbstractCatalogReadTool
{
    protected const string TOOL_NAME = 'read_settings';

    protected const string TOOL_DESCRIPTION = 'Read non-sensitive profile and integration settings.';

    protected const string RESOURCE = 'settings';

    protected const bool HAS_DETAIL = false;
}
