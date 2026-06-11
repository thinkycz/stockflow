<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\EmailVerification;

use App\Enums\GuardEnum;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Exceptions\GenericHttpException;
use Thinkycz\LaravelCore\Http\ApiFormRequest;
use Thinkycz\LaravelCore\Models\BaseUser;
use Thinkycz\LaravelCore\Routing\AutomaticController;
use Thinkycz\LaravelCore\Services\EmailBrokerService;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Parser;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;
use Thinkycz\LaravelCore\Validation\AuthValidity;

class EmailVerificationVerifyController extends AutomaticController
{
    /**
     * Mark the user's email address as verified.
     */
    public function __invoke(ApiFormRequest $request): SymfonyResponse
    {
        $validated = $this->validate($request);

        $guard = $validated->parseNullableString('guard') ?? $this->getDefaultGuard();

        $userProvider = Resolver::resolveEloquentUserProvider($guard);

        $user = Typer::assertNullableInstance($userProvider->retrieveByCredentials([
            'email' => $validated->assertString('email'),
        ]), BaseUser::class);

        if ($user instanceof BaseUser === false) {
            $request->thrower()
                ->error('email', PasswordBroker::INVALID_USER)
                ->throw();
        }

        if ($user instanceof MustVerifyEmail === false) {
            throw GenericHttpException::forbidden();
        }

        if ($user->hasVerifiedEmail()) {
            return Resolver::resolveResponseFactory()->noContent();
        }

        $tokenExists = EmailBrokerService::inject()->validate($user->getTable(), $user->getEmailForVerification(), $validated->assertString('token'));

        if ($tokenExists === false) {
            $request->thrower()
                ->error('token', PasswordBroker::INVALID_TOKEN)
                ->throw();
        }

        $user->markEmailAsVerified();

        Resolver::resolveEventDispatcher()->dispatch(new Verified($user));

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
                'token' => $authValidity->emailVerificationToken()->required(),
            ])
            ->guard(GuardEnum::values())
            ->validate();
    }

    /**
     * Get the default guard name.
     */
    protected function getDefaultGuard(): string
    {
        return Config::inject()->assertString('auth.defaults.guard');
    }
}
