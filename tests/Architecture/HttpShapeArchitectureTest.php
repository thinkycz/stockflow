<?php

declare(strict_types=1);

\arch('controllers return SymfonyResponse (not Illuminate\\Http\\Response)', function (): void {
    foreach (\glob(\base_path('app/Http/Controllers/Api/*/*.php')) as $file) {
        $contents = (string) \file_get_contents($file);

        \expect($contents)
            ->toContain('use Symfony\\Component\\HttpFoundation\\Response as SymfonyResponse')
            ->not->toContain('use Illuminate\\Http\\Response');
    }
});

\arch('controllers start with declare(strict_types=1)', function (): void {
    \expect('App\\Http\\Controllers')
        ->toUseStrictTypes();
});

\arch('app code uses strict equality (no == or !=)', function (): void {
    \expect('App')
        ->toUseStrictEquality();
});

\arch('api controllers are flat-action-suffixed (controller files have one of the action verbs)', function (): void {
    foreach (\glob(\base_path('app/Http/Controllers/Api/*/*.php')) as $file) {
        $class = \pathinfo($file, \PATHINFO_FILENAME);

        $matches = (bool) \preg_match('/(Index|Show|Store|Update|Destroy|Login|Register|Logout|Verify|Resend|Forgot|Reset)Controller$/', $class);

        \expect($matches)->toBeTrue("Controller file {$class} does not end with one of the action-suffix verbs.");
    }
});
