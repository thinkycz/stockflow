<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Password;

use App\Enums\GuardEnum;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Http\ApiFormRequest;
use Thinkycz\LaravelCore\Models\BaseUser;
use Thinkycz\LaravelCore\Routing\AutomaticController;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Parser;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;
use Thinkycz\LaravelCore\Validation\AuthValidity;

class PasswordForgotController extends AutomaticController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ApiFormRequest $request): SymfonyResponse
    {
        $validated = $this->validate($request);

        $this->hit($this->limit());

        $guard = $validated->parseNullableString('guard') ?? $this->getDefaultPasswordDriver();

        $userProvider = Resolver::resolveEloquentUserProvider($guard);

        $user = Typer::assertNullableInstance($userProvider->retrieveByCredentials([
            'email' => $validated->assertString('email'),
        ]), BaseUser::class);

        if ($user instanceof BaseUser === false) {
            $request->thrower()
                ->error('email', PasswordBroker::INVALID_USER)
                ->throw();
        }

        $sendRawPassword = $this->getSendRawPasswordConfig($guard);

        if ($sendRawPassword) {
            $password = Str::password(16);

            $user->update([
                'password' => $password,
            ]);

            $user->databaseTokens()->getQuery()->delete();

            $user->sendPasswordNewPasswordSettedNotification($password);
        } else {
            $passwordBroker = Resolver::resolvePasswordBroker($guard);

            $token = $passwordBroker->createToken($user);

            $user->sendPasswordResetNotification($token);
        }

        return Resolver::resolveResponseFactory()->noContent();
    }

    /**
     * Validate the incoming request.
     */
    protected function validate(ApiFormRequest $request): Parser
    {
        $authValidity = AuthValidity::inject();

        return $request->builder()
            ->rules([
                'email' => $authValidity->email()->required(),
            ])
            ->guard(GuardEnum::values())
            ->validate();
    }

    /**
     * Get the default password broker name.
     */
    protected function getDefaultPasswordDriver(): string
    {
        return Config::inject()->assertString('auth.defaults.passwords');
    }

    /**
     * Get the send raw password configuration value.
     */
    protected function getSendRawPasswordConfig(string $broker): bool
    {
        return Config::inject()->assertNullableBool("auth.passwords.{$broker}.send_raw_password") ?? false;
    }
}
