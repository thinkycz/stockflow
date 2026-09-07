<?php

declare(strict_types=1);

namespace App\Ai\Slack;

final class SlackTimestamp
{
    /**
     * Compare Slack decimal timestamps without losing microseconds or relying on digit widths.
     */
    public static function compare(string $left, string $right): int
    {
        $a = \explode('.', $left, 2);
        $b = \explode('.', $right, 2);

        $seconds = (int) $a[0] <=> (int) $b[0];

        return $seconds !== 0 ? $seconds : \strcmp(\mb_str_pad($a[1] ?? '', 6, '0'), \mb_str_pad($b[1] ?? '', 6, '0'));
    }
}
