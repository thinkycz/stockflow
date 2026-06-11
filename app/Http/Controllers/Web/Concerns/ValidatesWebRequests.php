<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Concerns;

use Illuminate\Http\Request;
use Thinkycz\LaravelCore\Support\Parser;
use Thinkycz\LaravelCore\Support\Resolver;

trait ValidatesWebRequests
{
    /**
     * Validate a web request and return typed access to the validated input.
     *
     * @param array<string, mixed> $rules
     */
    protected function validateRequest(Request $request, array $rules): Parser
    {
        return new Parser(Resolver::resolveValidator($request->all(), $rules)->validate());
    }
}
