<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Support;

use Illuminate\Support\Facades\Facade as IlluminateFacade;

class Facade extends IlluminateFacade
{
    /**
     * Get resolved instance.
     */
    public static function getResolved(string $abstract): object|null
    {
        return Typer::assertNullableObject(static::$resolvedInstance[$abstract] ?? null);
    }

    /**
     * Set resolved instance.
     *
     * @template T of object
     *
     * @param T $instance
     *
     * @return T
     */
    public static function setResolved(string $abstract, object $instance): object
    {
        return static::$resolvedInstance[$abstract] = $instance;
    }
}
