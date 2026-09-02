<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureApiCookieCsrf;

\test('csrf cookie controller returns a readable random double submit token', function (): void {
    $response = $this->getJson('/api/v1/csrf-cookie');

    $response->assertNoContent();
    $response->assertCookie(EnsureApiCookieCsrf::COOKIE_NAME);

    $cookie = $response->getCookie(EnsureApiCookieCsrf::COOKIE_NAME, false);
    \expect($cookie)->not->toBeNull()
        ->and($cookie?->isHttpOnly())->toBeFalse()
        ->and($cookie?->getValue())->toMatch('/^[A-Za-z0-9]{64}$/');
});
