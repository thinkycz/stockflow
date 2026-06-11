<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Support\Resolver;

class EnsureInertiaUserIsAuthenticated
{
    /**
     * Redirect unauthenticated web users to login; return 401 for JSON clients.
     *
     * @param Closure(Request): SymfonyResponse $next
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        if (User::auth() instanceof User) {
            return $next($request);
        }

        if ($request->getRequestFormat() === 'json' || $request->expectsJson()) {
            throw new AuthenticationException();
        }

        return Resolver::resolveRedirector()->to('/login');
    }
}
