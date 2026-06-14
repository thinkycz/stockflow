<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Tests;

use Orchestra\Testbench\Concerns\CreatesApplication;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    use CreatesApplication;
}
