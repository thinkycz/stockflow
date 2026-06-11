<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Validation\Concerns;

trait HasValidityLimits
{
    public const int TINY_INT_MAX = 127;

    public const int TINY_INT_MIN = -128;

    public const int UNSIGNED_TINY_INT_MAX = 255;

    public const int UNSIGNED_TINY_INT_MIN = 0;

    public const int SMALL_INT_MAX = 32767;

    public const int SMALL_INT_MIN = -32768;

    public const int UNSIGNED_SMALL_INT_MAX = 65535;

    public const int UNSIGNED_SMALL_INT_MIN = 0;

    public const int MEDIUM_INT_MAX = 8388607;

    public const int MEDIUM_INT_MIN = -8388608;

    public const int UNSIGNED_MEDIUM_INT_MAX = 16777215;

    public const int UNSIGNED_MEDIUM_INT_MIN = 0;

    public const int INT_MAX = 2147483647;

    public const int INT_MIN = -2147483648;

    public const int UNSIGNED_INT_MAX = 4294967295;

    public const int UNSIGNED_INT_MIN = 0;

    public const int BIG_INT_MAX = \PHP_INT_MAX;

    public const int BIG_INT_MIN = \PHP_INT_MIN;

    public const int UNSIGNED_BIG_INT_MAX = \PHP_INT_MAX;

    public const int UNSIGNED_BIG_INT_MIN = 0;

    public const int TINY_TEXT_MAX = 256;

    public const int TEXT_MAX = 65535;

    public const int MEDIUM_TEXT_MAX = 16777215;

    public const int LONG_TEXT_MAX = 4294967295;

    public const int VARCHAR_MAX = 65535;
}
