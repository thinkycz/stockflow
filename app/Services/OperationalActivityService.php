<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OperationalActivityTypeEnum;
use App\Events\OperationalActivityEvent;
use App\Models\Store;
use App\Models\User;
use Thinkycz\LaravelCore\Support\Resolver;

class OperationalActivityService
{
    /**
     * Dispatch a scalar operational snapshot to configured store destinations.
     *
     * @param list<array{store: Store, perspective: string|null}> $destinations
     * @param array<string, string> $facts
     */
    public static function dispatch(
        OperationalActivityTypeEnum $type,
        User $actor,
        string $occurredAt,
        string $url,
        array $destinations,
        array $facts,
    ): void {
        $routes = [];

        foreach ($destinations as $destination) {
            $channel = \mb_trim($destination['store']->getSlackChannel() ?? '');

            if ($channel === '') {
                continue;
            }

            $routes[] = [
                'channel' => $channel,
                'store' => $destination['store']->getName(),
                'perspective' => $destination['perspective'],
            ];
        }

        if ($routes === []) {
            return;
        }

        Resolver::resolveEventDispatcher()->dispatch(new OperationalActivityEvent(
            $type,
            $actor->getEmail(),
            $occurredAt,
            $url,
            $routes,
            $facts,
        ));
    }
}
