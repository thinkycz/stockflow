<?php

declare(strict_types=1);

\test('non-local deploy targets share a seed-free deployment recipe', function (): void {
    $makefile = \file_get_contents(\base_path('Makefile'));
    \expect($makefile)->toBeString()
        ->and($makefile)->toContain('development: deploy')
        ->and($makefile)->toContain('staging: deploy')
        ->and($makefile)->toContain('production: deploy')
        ->and($makefile)->toContain('${MAKE_ARTISAN} stockflow:identity:diagnose')
        ->and($makefile)->toContain('${MAKE_ARTISAN} stockflow:assistant:diagnose')
        ->and($makefile)->not->toContain('production: development');

    \preg_match('/^deploy:[^\\n]*\\n((?:\\t[^\\n]*\\n)+)/m', $makefile, $matches);
    \expect($matches[1] ?? null)->toBeString()
        ->and($matches[1])->not->toContain('db:seed')
        ->and($matches[1])->not->toContain('${MAKE_ARTISAN} up');

    \preg_match('/^production:[^\\n]*\\n((?:\\t[^\\n]*\\n)+)/m', $makefile, $matches);
    \expect($matches[1] ?? null)->toBeString()
        ->and($matches[1])->toContain('stockflow:identity:diagnose')
        ->and($matches[1])->toContain('stockflow:assistant:diagnose')
        ->and($matches[1])->toEndWith("\t" . '${MAKE_ARTISAN} up' . "\n");
});

\test('production migrations never invoke demo seeders', function (): void {
    foreach (\glob(\database_path('migrations/*.php')) ?: [] as $migration) {
        $source = \file_get_contents($migration);
        \expect($source)->toBeString()
            ->not->toContain('Database\\Seeders\\')
            ->not->toContain('db:seed');
    }
});
