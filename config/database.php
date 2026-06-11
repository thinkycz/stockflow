<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Thinkycz\LaravelCore\Support\Env;

$env = Env::inject();

return [
    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => $env->parseNullableString('DB_CONNECTION') ?? 'mysql',

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'url' => null,
            'host' => $env->mustParseNullableString('DB_HOST') ?? '127.0.0.1',
            'port' => $env->mustParseNullableInt('DB_PORT') ?? 3306,
            'database' => $env->mustParseString('DB_DATABASE'),
            'username' => $env->mustParseString('DB_USERNAME'),
            'password' => $env->mustParseNullableString('DB_PASSWORD'),
            'unix_socket' => $env->mustParseNullableString('DB_SOCKET'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_0900_ai_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => [],
        ],

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => null,
            'database' => $env->parseNullableString('DB_DATABASE') ?? \database_path('database.sqlite'),
            'prefix' => '',
            'foreign_key_constraints' => $env->parseNullableBool('DB_FOREIGN_KEYS') ?? true,
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [
        'client' => 'phpredis',

        'options' => [
            'cluster' => 'redis',
            'prefix' => $env->parseNullableString('REDIS_PREFIX') ?? '{' . Str::slug($env->mustParseString('APP_NAME') . '_' . $env->appEnv() . '_database', '_') . '}',
            'persistent' => $env->parseNullableBool('REDIS_PERSISTENT') ?? false,
        ],

        'default' => [
            'url' => null,
            'host' => $env->mustParseNullableString('REDIS_HOST') ?? '127.0.0.1',
            'username' => $env->mustParseNullableString('REDIS_USERNAME'),
            'password' => $env->mustParseNullableString('REDIS_PASSWORD'),
            'port' => $env->mustParseNullableInt('REDIS_PORT') ?? 6379,
            'database' => '0',
            'scheme' => $env->parseNullableString('REDIS_SCHEME') ?? 'tcp',
        ],

        'cache' => [
            'url' => null,
            'host' => $env->mustParseNullableString('REDIS_HOST') ?? '127.0.0.1',
            'username' => $env->mustParseNullableString('REDIS_USERNAME'),
            'password' => $env->mustParseNullableString('REDIS_PASSWORD'),
            'port' => $env->mustParseNullableInt('REDIS_PORT') ?? 6379,
            'database' => '1',
            'scheme' => $env->parseNullableString('REDIS_SCHEME') ?? 'tcp',
        ],
    ],
];
