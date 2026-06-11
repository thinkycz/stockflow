<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SetRequestFormatMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): SymfonyResponse $next
     */
    public function handle(Request $request, Closure $next, string $format): SymfonyResponse
    {
        $request->setRequestFormat($format);

        return $next($request);
    }
}
