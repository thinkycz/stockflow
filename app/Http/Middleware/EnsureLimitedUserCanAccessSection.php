<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\LimitedUserSectionEnum;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class EnsureLimitedUserCanAccessSection
{
    /**
     * Allow administrators and users with the requested section enabled.
     *
     * @param Closure(Request): SymfonyResponse $next
     */
    public function handle(Request $request, Closure $next, string $sectionValue): SymfonyResponse
    {
        $section = LimitedUserSectionEnum::tryFrom($sectionValue);

        if (!$section instanceof LimitedUserSectionEnum) {
            throw new RuntimeException('Unknown limited-user section middleware value: ' . $sectionValue);
        }

        $user = User::auth();

        if ($user instanceof User && $user->canAccessSection($section)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            \abort(403);
        }

        Inertia::flash('error', Typer::assertString(\__('You do not have permission for this section.')));

        return Resolver::resolveRedirector()->route('dashboard');
    }
}
