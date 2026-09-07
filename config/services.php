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

    'slack' => [
        'assistant' => [
            'enabled' => $env->parseBool('SLACK_ASSISTANT_ENABLED'),
            'admin_user_id' => $env->parseNullableInt('SLACK_ASSISTANT_ADMIN_USER_ID'),
            'workspace_id' => $env->parseNullableString('SLACK_ASSISTANT_WORKSPACE_ID'),
            'signing_secret' => $env->parseNullableString('SLACK_SIGNING_SECRET'),
            'bot_user_id' => $env->parseNullableString('SLACK_ASSISTANT_BOT_USER_ID'),
        ],
        'notifications' => [
            'bot_user_oauth_token' => $env->parseNullableString('SLACK_BOT_USER_OAUTH_TOKEN'),
        ],
    ],
];
