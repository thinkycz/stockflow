<?php

declare(strict_types=1);

namespace App\Enums;

enum FilesystemDiskEnum: string
{
    case Local = 'local';

    case Public = 'public';

    case Private = 'private';

    /**
     * Get possible values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return \array_column(self::cases(), 'value');
    }
}
