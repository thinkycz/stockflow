<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class EnsureUserIsAdmin
{
    /**
     * Redirect non-admin users back to the dashboard with a flash.
     *
     * @param Closure(Request): SymfonyResponse $next
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $user = User::auth();

        if ($user instanceof User && $user->isAdmin()) {
            return $next($request);
        }

        Inertia::flash('error', Typer::assertString(\__('You do not have permission for this section.')));

        return Resolver::resolveRedirector()->route('dashboard');
    }
}
