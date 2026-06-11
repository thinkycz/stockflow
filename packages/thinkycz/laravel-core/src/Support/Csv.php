<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Support;

class Csv
{
    /**
     * Encode value to CSV.
     */
    public static function value(mixed $value): string
    {
        if (\is_string($value)) {
            return '"' . \str_replace('"', '""', $value) . '"';
        }

        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value === null) {
            return '';
        }

        return (string) Typer::assertScalar($value);
    }

    /**
     * Encode line to CSV.
     *
     * @param array<mixed> $line
     */
    public static function line(array $line): string
    {
        return \implode(
            ',',
            \array_map(static function (mixed $value): string {
                return static::value($value);
            }, $line),
        );
    }
}
