<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Support;

use Illuminate\Foundation\Application;
use Illuminate\Support\Env as IlluminateEnv;
use Thinkycz\LaravelCore\Traits\AssertTrait;
use Thinkycz\LaravelCore\Traits\InjectTrait;
use Thinkycz\LaravelCore\Traits\ParseTrait;

class Env
{
    use AssertTrait;
    use InjectTrait;
    use ParseTrait;

    /**
     * App.
     */
    public Application $app;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->app = Resolver::resolveApp();
    }

    /**
     * Mixed getter.
     */
    public function mixed(string|null $key = null): mixed
    {
        $value = IlluminateEnv::get($key ?? '');

        if ($value === '') {
            return null;
        }

        return $value;
    }

    /**
     * App env getter.
     */
    public function appEnv(): string
    {
        return $this->mustParseString('APP_ENV');
    }

    /**
     * App env is.
     *
     * @param array<string> $envs
     */
    public function appEnvIs(array $envs): bool
    {
        return \in_array($this->appEnv(), $envs, true);
    }

    /**
     * App env map.
     *
     * @template T
     *
     * @param array<string, T> $mapping
     *
     * @return T
     */
    public function appEnvMap(array $mapping): mixed
    {
        return $mapping[$this->appEnv()];
    }
}
