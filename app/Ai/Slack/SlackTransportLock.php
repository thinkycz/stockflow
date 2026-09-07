<?php

declare(strict_types=1);

namespace App\Ai\Slack;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use LogicException;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;

final class SlackTransportLock
{
    /**
     * Use the same distributed lock store as the assistant, with a distinct namespace.
     */
    public function lock(string $key, int $seconds): Lock
    {
        $store = Resolver::resolveCacheManager()->store(Config::inject()->assertString('ai.assistant.lock_store'))->getStore();
        if (!$store instanceof LockProvider) {
            throw new LogicException('Slack assistant requires atomic cache locks.');
        }

        return $store->lock($key, $seconds);
    }
}
