<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;

final class RecordAssistantQueueHeartbeatJob implements ShouldQueue
{
    use Queueable;

    /**
     * The heartbeat must never be executed twice automatically.
     */
    public int $tries = 1;

    /**
     * Route the heartbeat through the same dedicated queue as assistant turns.
     */
    public function __construct()
    {
        $this->onConnection('assistant');
        $this->onQueue('assistant');
    }

    /**
     * Record proof that a worker consumed the assistant queue recently.
     */
    public function handle(): void
    {
        Resolver::resolveCacheManager()
            ->store(Config::inject()->assertString('ai.assistant.lock_store'))
            ->put('assistant:queue:heartbeat', \now()->toJSON(), \now()->addMinutes(10));
    }
}
