<?php

declare(strict_types=1);

use Thinkycz\LaravelCore\Support\Env;

\test('Env::inject returns a usable Env instance', function (): void {
    $env = Env::inject();

    \expect($env)->toBeInstanceOf(Env::class);
});

\test('Env::parseNullableString returns the value or null when unset', function (): void {
    $env = Env::inject();

    \expect($env->parseNullableString('PATH'))->toBeString();
    \expect($env->parseNullableString('STOCKFLOW_DOES_NOT_EXIST'))->toBeNull();
});
