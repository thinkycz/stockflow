<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Validation\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Pagination\Cursor;
use Thinkycz\LaravelCore\Support\Trans;

class CursorRule implements ValidationRule
{
    /**
     * @inheritDoc
     */
    public function validate(mixed $attribute, mixed $value, Closure $fail): void
    {
        $trans = Trans::inject();

        if (!\is_string($value)) {
            $fail($trans->assertString('validation.invalid'));

            return;
        }

        if (Cursor::fromEncoded($value) !== null) {
            return;
        }

        $fail($trans->assertString('validation.invalid'));
    }
}
