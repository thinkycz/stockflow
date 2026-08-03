<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Settings;

use App\Models\User;
use App\Notifications\SlackTestNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\AnonymousNotifiable;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class SlackTestController
{
    /**
     * Send a test Slack notification to the company's Slack channel.
     */
    public function __invoke(): RedirectResponse
    {
        $user = User::mustAuth();
        $channel = \mb_trim($user->getCompanySlackChannel() ?? '');

        if ($channel === '') {
            Inertia::flash('error', \__('Configure a Slack channel before sending a test notification.'));

            return Resolver::resolveRedirector()->route('settings.show');
        }

        Resolver::resolveNotificationFactory()->send(
            (new AnonymousNotifiable())->route('slack', $channel),
            new SlackTestNotification(Typer::assertString($user->getAttribute('email'))),
        );

        Inertia::flash('success', \__('Test Slack notification sent.'));

        return Resolver::resolveRedirector()->route('settings.show');
    }
}
