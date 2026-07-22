<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OperationalActivityEvent;
use App\Notifications\OperationalActivitySlackNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;
use Throwable;

class SendOperationalActivitySlackNotificationListener
{
    /**
     * Queue one Slack notification for every distinct configured channel.
     */
    public function handle(OperationalActivityEvent $event): void
    {
        $token = \mb_trim(Config::inject()->assertNullableString('services.slack.notifications.bot_user_oauth_token') ?? '');

        if ($token === '') {
            return;
        }

        $seen = [];

        foreach ($event->destinations as $destination) {
            $channel = \mb_trim($destination['channel']);

            if ($channel === '' || isset($seen[$channel])) {
                continue;
            }

            $seen[$channel] = true;

            try {
                Resolver::resolveNotificationFactory()->send(
                    (new AnonymousNotifiable())->route('slack', $channel),
                    new OperationalActivitySlackNotification(
                        $event->type,
                        $event->actorEmail,
                        $destination['store'],
                        $destination['perspective'],
                        $event->occurredAt,
                        $event->facts,
                        $event->url,
                    ),
                );
            } catch (Throwable $exception) {
                Resolver::resolveExceptionHandler()->report($exception);
            }
        }
    }
}
