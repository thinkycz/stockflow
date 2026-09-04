<?php

declare(strict_types=1);

\arch('domains do not depend on transport adapters')
    ->expect('App\\Domain')
    ->not->toUse(['App\\Http\\Controllers', 'App\\Ai']);

\test('domain dependencies follow the approved acyclic module graph', function (): void {
    $allowed = [
        'Identity' => [], 'Stores' => ['Inventory', 'Checklists'], 'Catalog' => [],
        'Inventory' => [], 'Statements' => ['Workforce'], 'Workforce' => [],
        'Payroll' => ['Workforce'], 'Finance' => ['Payroll', 'Workforce'],
        'BankStatements' => [], 'Recipes' => [], 'GiftVouchers' => [],
        'Checklists' => [], 'Noticeboard' => [], 'OperationalActivity' => ['Finance'],
    ];
    foreach (\glob(\base_path('app/Domain/*/*.php')) ?: [] as $file) {
        $module = \basename(\dirname($file));
        \expect($allowed)->toHaveKey($module);
        \preg_match_all('/use App\\\\Domain\\\\([A-Za-z]+)\\\\/', (string) \file_get_contents($file), $matches);
        foreach ($matches[1] as $dependency) {
            if ($dependency !== $module) {
                \expect($allowed[$module])->toContain($dependency);
            }
        }
    }
    $visit = function (string $module, array $path) use (&$visit, $allowed): void {
        \expect($path)->not->toContain($module);
        foreach ($allowed[$module] as $dependency) {
            $visit($dependency, [...$path, $module]);
        }
    };
    foreach (\array_keys($allowed) as $module) {
        $visit($module, []);
    }
});

\test('obsolete flat application implementations are absent', function (): void {
    \expect(\glob(\base_path('app/Services/*.php')) ?: [])->toBe([])
        ->and(\glob(\base_path('app/Operations/*/*.php')) ?: [])->toBe([]);
});

\test('core framework sources and scaffolds remain independent of application domains', function (): void {
    foreach (['src', 'stubs'] as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(\base_path('packages/thinkycz/laravel-core/' . $directory), FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                \expect((string) \file_get_contents($file->getPathname()))->not->toContain('App\\Domain\\');
            }
        }
    }
});
