<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class GenericHttpException extends HttpException
{
    /**
     * Unauthorized.
     */
    public static function unauthorized(): self
    {
        return new self(401, 'Unauthorized');
    }

    /**
     * Forbidden.
     */
    public static function forbidden(): self
    {
        return new self(403, 'Forbidden');
    }

    /**
     * Must be guest.
     */
    public static function mustBeGuest(): self
    {
        return new self(427, 'Must Be Guest');
    }
}
