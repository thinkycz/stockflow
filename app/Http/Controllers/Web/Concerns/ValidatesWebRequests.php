<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Thinkycz\LaravelCore\Support\Parser;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;

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

    /**
     * Throw a validation error for the given field.
     */
    protected function throwValidationError(string $field, string $message): never
    {
        $thrower = new Thrower(Resolver::resolveValidatorFactory()->make([], []));
        $thrower->message($field, $message);
        $thrower->throw();
    }

    /**
     * Build a standard pagination metadata array from a paginator.
     *
     * @return array{current_page: int, last_page: int, per_page: int, total: int}
     */
    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
