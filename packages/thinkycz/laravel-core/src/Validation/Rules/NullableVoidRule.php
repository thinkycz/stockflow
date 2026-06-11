<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Validation\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NullableVoidRule implements ValidationRule
{
    /**
     * Create a new rule instance.
     *
     * @param Closure(mixed, mixed=): void $callback
     */
    public function __construct(protected Closure $callback) {}

    /**
     * @inheritDoc
     */
    public function validate(mixed $attribute, mixed $value, Closure $fail): void
    {
        ($this->callback)($value, $attribute);
    }
}
