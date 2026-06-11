<?php

declare(strict_types=1);

\arch('app classes have all methods documented (replaces FunctionRequired)', function (): void {
    \expect('App')
        ->toHaveMethodsDocumented();
});

\arch('app classes have all properties documented (replaces PropertyRequired)', function (): void {
    \expect('App')
        ->toHavePropertiesDocumented();
});

\arch('core package classes have all methods documented', function (): void {
    \expect('Thinkycz\\LaravelCore')
        ->toHaveMethodsDocumented();
});

\arch('core package classes have all properties documented', function (): void {
    \expect('Thinkycz\\LaravelCore')
        ->toHavePropertiesDocumented();
});
