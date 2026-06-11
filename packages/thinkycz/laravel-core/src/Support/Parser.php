<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\ValidatedInput;
use Thinkycz\LaravelCore\Traits\AssertTrait;
use Thinkycz\LaravelCore\Traits\ParserTrait;
use Thinkycz\LaravelCore\Traits\ParseTrait;

class Parser extends ValidatedInput
{
    use AssertTrait;
    use ParserTrait;
    use ParseTrait;

    /**
     * Get input from headers.
     */
    public static function fromHeaders(): self
    {
        return new self(Resolver::resolveRequest()->headers->all());
    }

    /**
     * Get input from request.
     */
    public static function fromRequest(): self
    {
        return new self(Resolver::resolveRequest()->all());
    }

    /**
     * Get input from route.
     */
    public static function fromRoute(): self
    {
        return new self(Resolver::resolveRoute()->parameters ?? []);
    }

    /**
     * Get input from cookies.
     */
    public static function fromCookies(): self
    {
        return new self(Resolver::resolveRequest()->cookies->all());
    }

    /**
     * Get input from files.
     */
    public static function fromFiles(): self
    {
        return new self(Resolver::resolveRequest()->allFiles());
    }

    /**
     * Get input from request attributes.
     */
    public static function fromAttributes(): self
    {
        return new self(Resolver::resolveRequest()->attributes->all());
    }

    /**
     * Get input from session.
     */
    public static function fromSession(): self
    {
        return new self(Resolver::resolveRequest()->session()->all());
    }

    /**
     * Mixed getter.
     */
    public function mixed(string|null $key = null): mixed
    {
        if ($key === null) {
            return $this->input;
        }

        return Arr::get($this->input, $key);
    }
}
