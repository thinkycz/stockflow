<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Validation\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\Response;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Trans;
use Thinkycz\LaravelCore\Support\Typer;

class RecaptchaRule implements ValidationRule
{
    /**
     * Create a new rule instance.
     */
    public function __construct(protected string $secret, protected string $message = 'validation.invalid') {}

    /**
     * @inheritDoc
     */
    public function validate(mixed $attribute, mixed $value, Closure $fail): void
    {
        if (Config::inject()->appEnvIs(['local', 'testing'])) {
            return;
        }

        $response = Typer::assertInstance(
            Resolver::resolveHttpClientFactory()
                ->createPendingRequest()
                ->accept('application/json')
                ->asForm()
                ->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => $this->secret,
                    'response' => $value,
                ]),
            Response::class,
        );

        if ($response->successful() && $response->json('success') === true) {
            return;
        }

        $fail(Trans::inject()->assertString($this->message));
    }
}
