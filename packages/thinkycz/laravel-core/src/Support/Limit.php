<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Support;

use Closure;
use Illuminate\Cache\RateLimiting\Limit as IlluminateLimit;
use Symfony\Component\HttpFoundation\Response;
use Thinkycz\LaravelCore\Http\RequestSignature;

class Limit extends IlluminateLimit
{
    /**
     * @inheritDoc
     *
     * @phpstan-ignore-next-line
     */
    public function __construct(string $key, int $maxAttempts, int $decaySeconds, Closure|null $responseCallback = null)
    {
        parent::__construct($key, $maxAttempts, $decaySeconds);

        // @phpstan-ignore-next-line
        $this->responseCallback = $responseCallback;
    }

    /**
     * Default constructor.
     *
     * @phpstan-ignore-next-line
     */
    public static function default(string $key = '', int $maxAttempts = 3, int $decaySeconds = 60, Closure|null $responseCallback = null): self
    {
        return new self(RequestSignature::default($key)->hash(), $maxAttempts, $decaySeconds, $responseCallback);
    }

    /**
     * Response callback getter.
     *
     * @return Closure(int): never|Closure(int): Response|null
     */
    public function getResponseCallback(): Closure|null
    {
        $callback = $this->responseCallback;

        return $callback instanceof Closure ? $callback : null;
    }
}
