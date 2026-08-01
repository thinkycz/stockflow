<?php

declare(strict_types=1);

namespace App\Enums;

enum ChecklistTemplateScopeEnum: string
{
    case Daily = 'daily';

    case Weekly = 'weekly';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return \array_column(self::cases(), 'value');
    }
}
