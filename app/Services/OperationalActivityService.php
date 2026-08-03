<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OperationalActivityTypeEnum;
use App\Events\OperationalActivityEvent;
use App\Models\OperationalActivity;
use App\Models\Store;
use App\Models\User;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

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
        $company = $actor->resolveScopeUser();
        $storeContexts = [];
        foreach ($destinations as $destination) {
            $storeContexts[] = [
                'store_id' => $destination['store']->getKey(),
                'store_name' => $destination['store']->getName(),
                'perspective' => $destination['perspective'],
            ];
        }

        Typer::assertInstance(OperationalActivity::query()->create([
            'company_user_id' => $company->getKey(),
            'type' => $type->value,
            'actor_email' => $actor->getEmail(),
            'occurred_at' => $occurredAt,
            'url' => $url,
            'store_contexts' => $storeContexts,
            'facts' => $facts,
        ]), OperationalActivity::class);

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

    /**
     * Dispatch a scalar operational snapshot to the company-wide channel.
     *
     * @param array<string, string> $facts
     */
    public static function dispatchToCompany(
        OperationalActivityTypeEnum $type,
        User $actor,
        string $occurredAt,
        string $url,
        array $facts,
    ): void {
        $company = $actor->resolveScopeUser();
        Typer::assertInstance(OperationalActivity::query()->create([
            'company_user_id' => $company->getKey(),
            'type' => $type->value,
            'actor_email' => $actor->getEmail(),
            'occurred_at' => $occurredAt,
            'url' => $url,
            'store_contexts' => [],
            'facts' => $facts,
        ]), OperationalActivity::class);

        $channel = \mb_trim($company->getCompanySlackChannel() ?? '');

        if ($channel === '') {
            return;
        }

        Resolver::resolveEventDispatcher()->dispatch(new OperationalActivityEvent(
            $type,
            $actor->getEmail(),
            $occurredAt,
            $url,
            [['channel' => $channel, 'store' => null, 'perspective' => null]],
            $facts,
        ));
    }
}
