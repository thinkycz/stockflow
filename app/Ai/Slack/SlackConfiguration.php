<?php

declare(strict_types=1);

namespace App\Ai\Slack;

use App\Models\User;
use Thinkycz\LaravelCore\Support\Config;

final class SlackConfiguration
{
    /**
     * Fail closed until an administrator and all transport settings exist.
     */
    public function admin(): User|null
    {
        $config = Config::inject();
        if (!$config->assertBool('ai.assistant.enabled') || !$config->assertBool('services.slack.assistant.enabled')) {
            return null;
        }
        foreach (['workspace_id', 'signing_secret', 'bot_user_id'] as $key) {
            if (($config->assertNullableString('services.slack.assistant.' . $key) ?? '') === '') {
                return null;
            }
        }
        if (($config->assertNullableString('services.slack.notifications.bot_user_oauth_token') ?? '') === '') {
            return null;
        }
        $admin = User::query()->whereKey($config->assertNullableInt('services.slack.assistant.admin_user_id'))->first();

        return $admin instanceof User && $admin->isAdmin() ? $admin : null;
    }
}
