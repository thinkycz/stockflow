<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\LimitedUserSectionEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EnsureLimitedUserCanAccessStockMovementSection
{
    /**
     * Resolve the limited-user section from the shared movement mode.
     *
     * @param Closure(Request): SymfonyResponse $next
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $mode = $request->isMethod('GET') ? $request->query('mode') : $request->input('mode');
        $section = $mode === LimitedUserSectionEnum::INCOMING->value
            ? LimitedUserSectionEnum::INCOMING
            : LimitedUserSectionEnum::CONSUMPTION;

        return (new EnsureLimitedUserCanAccessSection())->handle($request, $next, $section->value);
    }
}
