<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

\test('production session cookie always satisfies the host prefix requirements', function (): void {
    $process = new Process([
        \PHP_BINARY,
        '-r',
        <<<'PHP'
            require 'vendor/autoload.php';
            require 'bootstrap/app.php';
            $session = require 'config/session.php';
            echo json_encode([
                'cookie' => $session['cookie'],
                'path' => $session['path'],
                'domain' => $session['domain'],
                'secure' => $session['secure'],
            ], JSON_THROW_ON_ERROR);
            PHP,
    ], \base_path(), [
        'APP_ENV' => 'production',
        'APP_NAME' => 'StockFlow',
        'SESSION_SECURE_COOKIE' => 'false',
    ]);
    $process->mustRun();

    /** @var array{cookie: string, path: string, domain: string|null, secure: bool} $session */
    $session = \json_decode($process->getOutput(), true, flags: \JSON_THROW_ON_ERROR);

    \expect($session['cookie'])->toStartWith('__Host-')
        ->and($session['path'])->toBe('/')
        ->and($session['domain'])->toBeNull()
        ->and($session['secure'])->toBeTrue();
});
