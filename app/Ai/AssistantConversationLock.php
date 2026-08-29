<?php

declare(strict_types=1);

namespace App\Ai;

use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;

final class AssistantConversationLock
{
    /**
     * Acquire one distributed execution lock for the lifetime of a streamed turn.
     */
    public function acquire(string $conversationId): Lock
    {
        $lock = $this->tryAcquire($conversationId);

        if (!$lock instanceof Lock) {
            \abort(409, 'This assistant conversation is already running in another tab.');
        }

        return $lock;
    }

    /**
     * Try to acquire a distributed execution lock without throwing an HTTP response.
     */
    public function tryAcquire(string $conversationId): Lock|null
    {
        $repository = Resolver::resolveCacheManager()
            ->store(Config::inject()->assertString('ai.assistant.lock_store'));
        $store = $repository->getStore();

        if (!$store instanceof LockProvider) {
            throw new LogicException('The assistant lock cache store must support atomic locks.');
        }

        $lock = $store->lock(
            'assistant:conversation:' . $conversationId,
            Config::inject()->assertInt('ai.assistant.timeout_seconds') + 10,
        );

        return $lock->get() === true ? $lock : null;
    }

    /**
     * Release the acquired lock after the streamed callback finishes.
     */
    public function protect(StreamedResponse $response, Lock $lock): StreamedResponse
    {
        $callback = $response->getCallback();

        if (!$callback instanceof Closure) {
            $lock->release();

            return $response;
        }

        $response->setCallback(static function () use ($callback, $lock): void {
            try {
                $callback();
            } finally {
                $lock->release();
            }
        });

        return $response;
    }
}
