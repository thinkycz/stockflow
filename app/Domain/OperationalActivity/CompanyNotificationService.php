<?php

declare(strict_types=1);

namespace App\Domain\OperationalActivity;

use App\Models\OperationalDailyDigest;
use App\Models\User;
use App\Notifications\SlackTestNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Thinkycz\LaravelCore\Support\Resolver;

class CompanyNotificationService
{
    /**
     * Update the company-wide Slack destination.
     */
    public function updateSlackChannel(User $actor, string|null $channel): User
    {
        $this->assertAdmin($actor);
        $actor->update(['company_slack_channel' => $channel]);

        return $actor->refresh();
    }

    /**
     * Send the normal test notification to the configured Slack channel.
     */
    public function testSlackChannel(User $actor): bool
    {
        $this->assertAdmin($actor);
        $channel = \mb_trim($actor->getCompanySlackChannel() ?? '');

        if ($channel === '') {
            return false;
        }

        Resolver::resolveNotificationFactory()->send(
            (new AnonymousNotifiable())->route('slack', $channel),
            new SlackTestNotification($actor->getEmail()),
        );

        return true;
    }

    /**
     * Requeue one failed daily Slack digest through its normal service.
     */
    public function retrySlackDigest(User $actor, OperationalDailyDigest $digest): void
    {
        $this->assertAdmin($actor);

        if ($digest->getCompanyUserId() !== $actor->getKey()) {
            \abort(404);
        }

        (new DailyOperationalDigestService())->retry($actor, $digest);
    }

    /**
     * Ensure the assistant actor is the main administrator.
     */
    private function assertAdmin(User $actor): void
    {
        if (!$actor->isAdmin()) {
            \abort(403);
        }
    }
}
