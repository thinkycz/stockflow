<?php

declare(strict_types=1);

namespace App\Enums;

enum NoticeboardCardColorEnum: string
{
    case Yellow = 'yellow';

    case Pink = 'pink';

    case Blue = 'blue';

    case Green = 'green';

    case Purple = 'purple';

    /**
     * Get possible values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return \array_column(self::cases(), 'value');
    }
}
