<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

\test('local assistant conversations use the atomic file lock store', function (): void {
    $process = new Process([
        \PHP_BINARY,
        '-r',
        <<<'PHP'
            require 'vendor/autoload.php';
            require 'bootstrap/app.php';
            $ai = require 'config/ai.php';
            echo $ai['assistant']['lock_store'];
            PHP,
    ], \base_path(), [
        'APP_ENV' => 'local',
        'APP_NAME' => 'Teacha',
    ]);
    $process->mustRun();

    \expect($process->getOutput())->toBe('file');
});

\test('deployed assistant conversations keep the Redis lock store', function (string $environment): void {
    $process = new Process([
        \PHP_BINARY,
        '-r',
        <<<'PHP'
            require 'vendor/autoload.php';
            require 'bootstrap/app.php';
            $ai = require 'config/ai.php';
            echo $ai['assistant']['lock_store'];
            PHP,
    ], \base_path(), [
        'APP_ENV' => $environment,
        'APP_NAME' => 'Teacha',
    ]);
    $process->mustRun();

    \expect($process->getOutput())->toBe('redis');
})->with(['development', 'staging', 'production']);
