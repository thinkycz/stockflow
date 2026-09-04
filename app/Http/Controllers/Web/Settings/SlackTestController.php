<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Settings;

use App\Domain\OperationalActivity\CompanyNotificationService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

class SlackTestController
{
    /**
     * Send a test Slack notification to the company's Slack channel.
     */
    public function __invoke(): RedirectResponse
    {
        $user = User::mustAuth();
        if (!(new CompanyNotificationService())->testSlackChannel($user)) {
            Inertia::flash('error', \__('Configure a Slack channel before sending a test notification.'));

            return Resolver::resolveRedirector()->route('settings.show');
        }

        Inertia::flash('success', \__('Test Slack notification sent.'));

        return Resolver::resolveRedirector()->route('settings.show');
    }
}
