<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Concerns;

use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Thinkycz\LaravelCore\Http\RequestSignature;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\ThrottleSupport;

trait ThrottlesWebRequests
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
     * Throttle limit keyed by the current request signature.
     */
    protected function limit(RequestSignature|null $signature = null): Limit
    {
        $signature = $signature instanceof RequestSignature ? $signature : new RequestSignature(Resolver::resolveRequest());

        return Limit::perMinutes(static::$decay, static::$throttle)->by($signature->hash());
    }
}
