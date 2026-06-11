<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Validation\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thinkycz\LaravelCore\Support\Trans;

class CallbackRule implements ValidationRule
{
    /**
     * Create a new rule instance.
     *
     * @param Closure(mixed, mixed=): (bool|int|string) $callback
     */
    public function __construct(protected Closure $callback, protected int|string $message = 'validation.invalid') {}

    /**
     * @inheritDoc
     */
    public function validate(mixed $attribute, mixed $value, Closure $fail): void
    {
        $passes = ($this->callback)($value, $attribute);

        $trans = Trans::inject();

        if (\is_string($passes)) {
            $fail($trans->assertString($passes));

            return;
        }

        if (\is_int($passes)) {
            throw new UnprocessableEntityHttpException('', null, $passes);
        }

        if (!$passes && \is_int($this->message)) {
            throw new UnprocessableEntityHttpException('', null, $this->message);
        }

        if ($passes) {
            return;
        }

        $fail($trans->assertString((string) $this->message));
    }
}
