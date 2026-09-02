<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

\test('cors origins omit an unset optional origin', function (): void {
    $process = new Process([
        \PHP_BINARY,
        '-r',
        <<<'PHP'
            require 'vendor/autoload.php';
            require 'bootstrap/app.php';
            $cors = require 'config/cors.php';
            echo json_encode($cors['allowed_origins'], JSON_THROW_ON_ERROR);
            PHP,
    ], \base_path(), [
        'APP_ENV' => 'testing',
        'APP_NAME' => 'StockFlow',
        'APP_URL' => 'https://stockflow.example',
        'CORS_ALLOWED_ORIGIN' => '',
    ]);
    $process->mustRun();

    \expect(\json_decode($process->getOutput(), true, flags: \JSON_THROW_ON_ERROR))
        ->toBe(['https://stockflow.example']);
});

\test('cors origins include a configured optional origin', function (): void {
    $process = new Process([
        \PHP_BINARY,
        '-r',
        <<<'PHP'
            require 'vendor/autoload.php';
            require 'bootstrap/app.php';
            $cors = require 'config/cors.php';
            echo json_encode($cors['allowed_origins'], JSON_THROW_ON_ERROR);
            PHP,
    ], \base_path(), [
        'APP_ENV' => 'testing',
        'APP_NAME' => 'StockFlow',
        'APP_URL' => 'https://stockflow.example',
        'CORS_ALLOWED_ORIGIN' => 'https://client.example',
    ]);
    $process->mustRun();

    \expect(\json_decode($process->getOutput(), true, flags: \JSON_THROW_ON_ERROR))
        ->toBe(['https://stockflow.example', 'https://client.example']);
});
