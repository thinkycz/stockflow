<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Thinkycz\LaravelCore\Support\Config;

class EnsureAiAssistantIsEnabled
{
    /**
     * Hide the assistant surface unless it is explicitly enabled.
     *
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Config::inject()->assertBool('ai.assistant.enabled')) {
            \abort(404);
        }

        return $next($request);
    }
}
