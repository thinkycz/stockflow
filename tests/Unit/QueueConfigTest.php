<?php

declare(strict_types=1);

use Illuminate\Support\Env;

\test('queue database storage follows the mysql application fallback', function (): void {
    $repository = Env::getRepository();
    $original = $repository->get('DB_CONNECTION');
    $originalEnv = $_ENV['DB_CONNECTION'] ?? null;
    $originalServer = $_SERVER['DB_CONNECTION'] ?? null;
    $repository->clear('DB_CONNECTION');
    unset($_ENV['DB_CONNECTION'], $_SERVER['DB_CONNECTION']);
    \putenv('DB_CONNECTION');

    try {
        /** @var array{batching: array{database: string}, failed: array{database: string}} $config */
        $config = require \config_path('queue.php');

        \expect($config['batching']['database'])->toBe('mysql')
            ->and($config['failed']['database'])->toBe('mysql');
    } finally {
        if ($original === null) {
            $repository->clear('DB_CONNECTION');
        } else {
            $repository->set('DB_CONNECTION', $original);
        }

        if ($originalEnv !== null) {
            $_ENV['DB_CONNECTION'] = $originalEnv;
        }

        if ($originalServer !== null) {
            $_SERVER['DB_CONNECTION'] = $originalServer;
        }
    }
});
