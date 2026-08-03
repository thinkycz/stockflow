<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Notifications\OperationalDailyDigestSlackNotification;
use Illuminate\Notifications\Events\NotificationSent;

class MarkOperationalDailyDigestSlackSentListener
{
    /**
     * Mark the immutable digest delivered only after the Slack channel succeeds.
     */
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'slack' || !$event->notification instanceof OperationalDailyDigestSlackNotification) {
            return;
        }

        $event->notification->markSent();
    }
}
