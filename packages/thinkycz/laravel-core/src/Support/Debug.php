<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Support;

class Debug
{
    /**
     * Encode data for debugging purposes.
     *
     * @param array<mixed> $data
     */
    public static function encode(array $data): string
    {
        $encoded = [];

        foreach ($data as $key => $value) {
            $encoded[] = $key . '(' . \get_debug_type($value) . '):' . static::encodeValue($value);
        }

        return \implode(' ', $encoded);
    }

    /**
     * Encode value to string.
     */
    public static function encodeValue(mixed $value): string
    {
        if (\is_string($value)) {
            return '"' . \str_replace('"', '""', $value) . '"';
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (\is_array($value)) {
            return 'array';
        }

        if (\is_object($value)) {
            return $value::class;
        }

        if (\is_callable($value)) {
            return 'callable';
        }

        if (\is_resource($value)) {
            return 'resource';
        }

        if (\is_scalar($value)) {
            return (string) $value;
        }

        Panicker::panic(__METHOD__);
    }
}
