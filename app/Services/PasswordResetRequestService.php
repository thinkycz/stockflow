<?php

declare(strict_types=1);

namespace App\Services;

use Thinkycz\LaravelCore\Models\BaseUser;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;
use Throwable;

final class PasswordResetRequestService
{
    /**
     * Create and deliver a password-reset token without revealing account existence.
     */
    public function send(string $brokerName, string $email): void
    {
        $providerName = Config::inject()->assertNullableString("auth.passwords.{$brokerName}.provider")
            ?? Config::inject()->assertString('auth.defaults.provider');
        $user = Typer::assertNullableInstance(
            Resolver::resolveEloquentUserProvider($providerName)->retrieveByCredentials(['email' => $email]),
            BaseUser::class,
        );

        if (!$user instanceof BaseUser) {
            return;
        }

        $broker = Resolver::resolvePasswordBroker($brokerName);
        $token = $broker->createToken($user);

        try {
            $user->sendPasswordResetNotification($token);
        } catch (Throwable $exception) {
            if ($broker->tokenExists($user, $token)) {
                $broker->deleteToken($user);
            }

            \report($exception);
        }
    }
}
