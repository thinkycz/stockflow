<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

use RuntimeException;
use Thinkycz\LaravelCore\Support\Config;

trait OnlyRunsInDemoEnvironment
{
    /**
     * Refuse demo data outside isolated local and testing environments.
     */
    protected function ensureDemoEnvironment(): void
    {
        if (!Config::inject()->appEnvIs(['local', 'testing'])) {
            throw new RuntimeException('Demo seeders may run only in local or testing environments.');
        }
    }
}
