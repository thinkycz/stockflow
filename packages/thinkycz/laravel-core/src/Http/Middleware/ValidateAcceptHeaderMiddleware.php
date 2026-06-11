<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;

class ValidateAcceptHeaderMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): SymfonyResponse $next
     */
    public function handle(Request $request, Closure $next, string ...$accepts): SymfonyResponse
    {
        if (!$request->hasHeader('Accept')) {
            return $next($request);
        }

        if (!$request->accepts($accepts)) {
            throw new NotAcceptableHttpException('Accept Header Invalid');
        }

        return $next($request);
    }
}
