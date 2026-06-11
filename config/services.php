<?php

declare(strict_types=1);

use Thinkycz\LaravelCore\Support\Env;

$env = Env::inject();

return [
    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'ses' => [
        'key' => $env->mustParseNullableString('AWS_ACCESS_KEY_ID'),
        'secret' => $env->mustParseNullableString('AWS_SECRET_ACCESS_KEY'),
        'region' => $env->mustParseNullableString('AWS_DEFAULT_REGION'),
    ],
];
