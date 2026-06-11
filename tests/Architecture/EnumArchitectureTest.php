<?php

declare(strict_types=1);

\arch('enums are string-backed', function (): void {
    \expect('App\\Enums')
        ->toBeStringBackedEnums();
});

\arch('enums live directly under App\\Enums (no subdirectories)', function (): void {
    \expect(\count(\glob(\base_path('app/Enums/*/*.php'))) === 0)->toBeTrue();
});

\arch('enums expose a public static values() method', function (): void {
    foreach (\glob(\base_path('app/Enums/*.php')) as $file) {
        $contents = (string) \file_get_contents($file);

        \expect($contents)
            ->toMatch('/public static function values\\(\\): array/');
    }
});
