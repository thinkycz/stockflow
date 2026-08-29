<?php

declare(strict_types=1);

use Thinkycz\LaravelCore\Support\Env;

$env = Env::inject();

return [
    'default' => 'openrouter',

    'providers' => [
        'openrouter' => [
            'driver' => 'openrouter',
            'key' => $env->parseNullableString('OPENROUTER_API_KEY'),
            'url' => $env->parseNullableString('OPENROUTER_URL') ?? 'https://openrouter.ai/api/v1',
            'models' => [
                'text' => [
                    'default' => $env->parseNullableString('OPENROUTER_MODEL') ?? 'minimax/minimax-m3:free',
                ],
            ],
        ],
    ],

    'conversations' => [
        'connection' => null,
        'generate_title' => true,
        'tables' => [
            'conversations' => 'agent_conversations',
            'messages' => 'agent_conversation_messages',
        ],
    ],

    'assistant' => [
        'enabled' => $env->parseBool('AI_ASSISTANT_ENABLED'),
        'lock_store' => $env->appEnvMap([
            'testing' => 'array',
            'local' => 'file',
            'development' => 'redis',
            'staging' => 'redis',
            'production' => 'redis',
        ]),
        'prompt_max_characters' => $env->parseNullableInt('AI_ASSISTANT_PROMPT_MAX_CHARACTERS') ?? 10000,
        'max_steps' => $env->parseNullableInt('AI_ASSISTANT_MAX_STEPS') ?? 12,
        'timeout_seconds' => $env->parseNullableInt('AI_ASSISTANT_TIMEOUT_SECONDS') ?? 120,
        'rate_limit_per_minute' => $env->parseNullableInt('AI_ASSISTANT_RATE_LIMIT_PER_MINUTE') ?? 20,
        'tool_result_limit' => $env->parseNullableInt('AI_ASSISTANT_TOOL_RESULT_LIMIT') ?? 50,
    ],
];
