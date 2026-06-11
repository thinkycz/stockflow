<?php

declare(strict_types=1);

namespace Tests;

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
