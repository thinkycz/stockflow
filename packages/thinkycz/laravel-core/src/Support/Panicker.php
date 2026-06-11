<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Support;

use RuntimeException;
use Throwable;

class Panicker
{
    /**
     * Panic.
     *
     * @param array<mixed> $args
     */
    public static function panic(string $source, string $message = 'panic', array $args = [], int $code = 0, Throwable|null $previous = null): never
    {
        throw new RuntimeException(static::message($source, $message, $args), $code, $previous);
    }

    /**
     * Message.
     *
     * @param array<mixed> $args
     */
    public static function message(string $source, string $message, array $args): string
    {
        $msg = "[{$source}] - {$message}";

        if (\count($args) === 0) {
            return $msg;
        }

        return $msg . ' | ' . Debug::encode($args);
    }
}
