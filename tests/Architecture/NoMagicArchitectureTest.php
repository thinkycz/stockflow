<?php

declare(strict_types=1);

\arch('migrations do not call Blueprint polymorphic relation methods', function (): void {
    $forbidden = [
        '->morphs(',
        '->nullableMorphs(',
        '->nullableNumericMorphs(',
        '->nullableUlidMorphs(',
        '->nullableUuidMorphs(',
        '->numericMorphs(',
        '->ulidMorphs(',
        '->uuidMorphs(',
    ];

    foreach (\glob(\base_path('database/migrations/*.php')) as $file) {
        $contents = (string) \file_get_contents($file);

        foreach ($forbidden as $method) {
            \expect($contents)
                ->not->toContain($method);
        }
    }
});

\arch('app models do not use Eloquent model event methods', function (): void {
    $forbidden = [
        '::observe(',
        '::retrieved(',
        '::saving(',
        '::saved(',
        '::updating(',
        '::updated(',
        '::creating(',
        '::created(',
        '::replicating(',
        '::deleting(',
        '::deleted(',
    ];

    foreach (\glob(\base_path('app/Models/*.php')) as $file) {
        $contents = (string) \file_get_contents($file);

        foreach ($forbidden as $method) {
            \expect($contents)
                ->not->toContain($method);
        }
    }
});

\arch('app code does not use Dispatchable or DispatchesJobs traits', function (): void {
    $forbidden = [
        'use Dispatchable;',
        'use DispatchesJobs;',
        'use Illuminate\\Foundation\\Bus\\Dispatchable;',
        'use Illuminate\\Foundation\\Bus\\DispatchesJobs;',
        'use Illuminate\\Foundation\\Events\\Dispatchable;',
    ];

    foreach (\glob(\base_path('app/**/*.php'), \GLOB_BRACE) as $file) {
        $contents = (string) \file_get_contents($file);

        foreach ($forbidden as $use) {
            \expect($contents)
                ->not->toContain($use);
        }
    }
});
