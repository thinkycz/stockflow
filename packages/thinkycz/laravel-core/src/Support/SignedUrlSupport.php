<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Support;

use Illuminate\Support\Carbon;

class SignedUrlSupport
{
    /**
     * Make signed or temporary signed action url.
     *
     * @param array<mixed> $parameters
     */
    public static function make(string $action, array $parameters, int $expires): string
    {
        if ($expires > 0) {
            $parameters['expires'] = Carbon::now()->addMinutes($expires)->getTimestamp();
        }

        \ksort($parameters);

        return Resolver::resolveUrlGenerator()->action(
            $action,
            \array_replace($parameters, [
                'signature' => \hash_hmac('sha256', Resolver::resolveUrlGenerator()->action($action, $parameters), Config::inject()->assertString('app.key')),
            ]),
        );
    }
}
