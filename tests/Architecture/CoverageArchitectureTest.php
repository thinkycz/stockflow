<?php

declare(strict_types=1);

/**
 * Coverage architecture test.
 *
 * `docs/guidelines.md:51` mandates "Every controller must have at
 * least one feature test." That rule was previously unenforced; this
 * test scans `app/Http/Controllers/{Web,Api}` and asserts a matching
 * `*ControllerTest.php` exists under `tests/Feature/...`.
 *
 * Concerns (traits under `app/Http/Controllers/Web/Concerns/`) are
 * excluded.
 */
\arch('every web controller has a feature test', function (): void {
    $controllerFiles = \glob(\base_path('app/Http/Controllers/Web/*/*.php')) ?: [];

    foreach ($controllerFiles as $file) {
        $parent = \basename(\dirname($file));
        $shortName = \basename($file, '.php');

        if ($parent === 'Concerns') {
            continue;
        }

        $expectedTest = \str_replace(
            \base_path('app/Http/Controllers/Web/'),
            \base_path('tests/Feature/App/Http/Controllers/Web/'),
            \dirname($file) . '/' . $shortName . 'Test.php',
        );

        \expect(\is_file($expectedTest))
            ->toBeTrue("Missing feature test for {$shortName}. Expected: {$expectedTest}");
    }
});

\arch('every api controller has a feature test', function (): void {
    $controllerFiles = \glob(\base_path('app/Http/Controllers/Api/*/*.php')) ?: [];

    foreach ($controllerFiles as $file) {
        $shortName = \basename($file, '.php');
        $expectedTest = \str_replace(
            \base_path('app/Http/Controllers/Api/'),
            \base_path('tests/Feature/App/Http/Controllers/Api/'),
            \dirname($file) . '/' . $shortName . 'Test.php',
        );

        \expect(\is_file($expectedTest))
            ->toBeTrue("Missing feature test for {$shortName}. Expected: {$expectedTest}");
    }
});
