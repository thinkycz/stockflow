<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Support;

class Hash
{
    /**
     * Encode data to hash.
     *
     * @param array<mixed> $data
     */
    public static function encode(array $data): string
    {
        return \hash('sha256', \serialize($data));
    }
}
