<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Thinkycz\LaravelCore\Models\BaseUser;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class PasswordResetService
{
    /**
     * Consume one reset token under a database lock and revoke prior sessions.
     *
     * @return BaseUser|string a locked user on success or a PasswordBroker error key
     */
    public function reset(
        string $passwordDriver,
        string $userProviderName,
        string $email,
        string $token,
        string $password,
    ): BaseUser|string {
        $provider = Resolver::resolveEloquentUserProvider($userProviderName);
        $user = Typer::assertNullableInstance(
            $provider->retrieveByCredentials(['email' => $email]),
            BaseUser::class,
        );
        if (!$user instanceof BaseUser) {
            return PasswordBroker::INVALID_USER;
        }

        $broker = Resolver::resolvePasswordBroker($passwordDriver);
        $table = Config::inject()->assertString("auth.passwords.{$passwordDriver}.table");

        return DB::transaction(function () use ($provider, $user, $broker, $table, $email, $token, $password): BaseUser|string {
            $tokenRow = DB::table($table)->where('email', $email)->lockForUpdate()->first();
            if ($tokenRow === null) {
                return PasswordBroker::INVALID_TOKEN;
            }

            $lockedUser = Typer::assertNullableInstance(
                $user->newQuery()->whereKey($user->getKey())->lockForUpdate()->first(),
                BaseUser::class,
            );
            if (!$lockedUser instanceof BaseUser) {
                return PasswordBroker::INVALID_USER;
            }
            if (!$broker->tokenExists($lockedUser, $token)) {
                return PasswordBroker::INVALID_TOKEN;
            }

            $lockedUser->update(['password' => $password]);
            if ($lockedUser->getRememberToken() !== '') {
                $provider->updateRememberToken($lockedUser, Str::random(60));
            }
            $lockedUser->databaseTokens()->getQuery()->delete();
            $broker->deleteToken($lockedUser);

            return $lockedUser;
        });
    }
}
