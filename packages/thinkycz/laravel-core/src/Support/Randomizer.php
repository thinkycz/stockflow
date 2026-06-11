<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Support;

use Illuminate\Support\Str;

class Randomizer
{
    /**
     * Generate random token.
     */
    public static function token(int $length = 64): string
    {
        return Str::random($length);
    }

    /**
     * Generate random code.
     */
    public static function numeric(int $length = 6, bool $startWithZero = true): string
    {
        $code = (string) \random_int($startWithZero ? 0 : 1, 9);

        for ($i = 1; $i < $length; ++$i) {
            $code .= \random_int(0, 9);
        }

        return $code;
    }

    /**
     * Generate random token/code from source.
     *
     * @param array<string> $source
     */
    public static function source(int $length = 6, array $source = []): string
    {
        $code = '';

        if (\count($source) === 0) {
            $source = \range('a', 'z');
        }

        $last = \count($source) - 1;

        for ($i = 0; $i < $length; ++$i) {
            $code .= $source[\random_int(0, $last)];
        }

        return $code;
    }
}
