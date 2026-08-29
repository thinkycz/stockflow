<?php

declare(strict_types=1);

namespace App\Enums;

enum AssistantActionClassificationEnum: string
{
    case READ = 'read';

    case MUTATION = 'mutation';

    case EXTERNAL_SIDE_EFFECT = 'external_side_effect';

    /**
     * Get possible values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return \array_map(static fn(self $classification): string => $classification->value, self::cases());
    }
}
