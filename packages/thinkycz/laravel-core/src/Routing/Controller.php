<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Routing;

use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Routing\Controller as IlluminateController;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Http\RequestSignature;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\ThrottleSupport;

class Controller extends IlluminateController
{
    /**
     * Throttle max attempts.
     */
    public static int $throttle = 5;

    /**
     * Throttle decay in minutes.
     */
    public static int $decay = 15;

    /**
     * @inheritDoc
     *
     * @param array<mixed> $parameters
     */
    public function callAction(mixed $method, mixed $parameters): SymfonyResponse
    {
        return parent::callAction($method, $parameters);
    }

    /**
     * Register throttle.
     *
     * @param (Closure(int): never)|null $onError
     *
     * @return array{Closure(): void, Closure(): void}
     */
    protected function throttle(Limit $limit, Closure|null $onError = null): array
    {
        return ThrottleSupport::throttle($limit, $onError);
    }

    /**
     * Register throttle and hit.
     *
     * @param (Closure(int): never)|null $onError
     *
     * @return Closure(): void
     */
    protected function hit(Limit $limit, Closure|null $onError = null): Closure
    {
        return ThrottleSupport::hit($limit, $onError);
    }

    /**
     * Throttle limit.
     */
    protected function limit(RequestSignature|null $signature = null): Limit
    {
        $signature = $signature instanceof RequestSignature ? $signature : new RequestSignature(Resolver::resolveRequest());

        return Limit::perMinutes(static::$decay, static::$throttle)->by($signature->hash());
    }
}
