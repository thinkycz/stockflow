<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Validation\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Trans;

class IcoRule implements ValidationRule
{
    /**
     * Create a new rule instance.
     */
    public function __construct(protected bool $validateAres = true, protected int|null $cacheDuration = 86400) {}

    /**
     * @inheritDoc
     */
    public function validate(mixed $attribute, mixed $value, Closure $fail): void
    {
        $trans = Trans::inject();

        if (!\is_string($value)) {
            $fail($trans->assertString('validation.regex'));

            return;
        }

        if (Config::inject()->appEnvIs(['testing'])) {
            return;
        }

        if (\preg_match('/^\\d{8}$/', $value) !== 1) {
            $fail($trans->assertString('validation.regex'));

            return;
        }

        if (!$this->validateChecksum($value)) {
            $fail($trans->assertString('validation.regex'));
        }
    }

    /**
     * Validate cheksum.
     */
    protected function validateChecksum(string $value): bool
    {
        $cheksum = 0;

        for ($i = 0; $i < 7; ++$i) {
            $cheksum += (int) $value[$i] * (8 - $i);
        }

        $cheksum %= 11;

        if ($cheksum === 0) {
            $controll = 1;
        } elseif ($cheksum === 1) {
            $controll = 0;
        } else {
            $controll = 11 - $cheksum;
        }

        return $controll === (int) $value[7];
    }
}
