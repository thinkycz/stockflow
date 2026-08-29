<?php

declare(strict_types=1);

namespace App\Ai\Tools;

final class ReadRecipeTestsTool extends AbstractCatalogReadTool
{
    protected const string TOOL_NAME = 'read_recipe_tests';

    protected const string TOOL_DESCRIPTION = 'Read recipe test sessions and attempts.';

    protected const string RESOURCE = 'recipe_tests';
}
