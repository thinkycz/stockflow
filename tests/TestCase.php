<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Cached Inertia asset version for the current test process.
     */
    protected static string|null $inertiaVersion = null;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // CSRF is exercised by the framework's own test suite; our
        // feature tests authenticate via `$this->be(...)` and do not
        // spin up a real session, so the CSRF check would 419 every
        // POST. Disabling it here keeps the suite focused on the
        // behaviour we actually want to assert.
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    /**
     * Headers for an Inertia JSON page request.
     *
     * @return array<string, string>
     */
    protected function inertiaHeaders(): array
    {
        if (static::$inertiaVersion === null) {
            $manifest = \public_path('build/manifest.json');

            if (\is_file($manifest)) {
                $hash = \hash_file('xxh128', $manifest);

                static::$inertiaVersion = \is_string($hash) ? $hash : 'fallback';
            } else {
                static::$inertiaVersion = 'fallback';
            }
        }

        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => static::$inertiaVersion,
        ];
    }
}
