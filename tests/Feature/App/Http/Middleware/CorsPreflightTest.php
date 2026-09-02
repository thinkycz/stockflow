<?php

declare(strict_types=1);

use Thinkycz\LaravelCore\Support\Config;

\test('api preflight succeeds when no optional cors origin is configured', function (): void {
    Config::inject()->assign('cors.allowed_origins', ['https://stockflow.example']);

    $this->withHeaders([
        'Origin' => 'https://stockflow.example',
        'Access-Control-Request-Method' => 'GET',
    ])->options('/api/v1/csrf-cookie')
        ->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', 'https://stockflow.example');
});
