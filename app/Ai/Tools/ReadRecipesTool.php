<?php

declare(strict_types=1);

namespace App\Ai\Tools;

final class ReadRecipesTool extends AbstractCatalogReadTool
{
    protected const string TOOL_NAME = 'read_recipes';

    protected const string TOOL_DESCRIPTION = 'Read recipe categories and recipes.';

    protected const string RESOURCE = 'recipes';

    protected const bool SEARCHABLE = true;
}
