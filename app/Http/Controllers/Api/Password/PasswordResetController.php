<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Password;

use App\Domain\Identity\PasswordResetService;
use App\Enums\GuardEnum;
use Illuminate\Contracts\Auth\PasswordBroker;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Http\ApiFormRequest;
use Thinkycz\LaravelCore\Models\BaseUser;
use Thinkycz\LaravelCore\Routing\AutomaticController;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Parser;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;
use Thinkycz\LaravelCore\Validation\AuthValidity;

class PasswordResetController extends AutomaticController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ApiFormRequest $request): SymfonyResponse
    {
        $validated = $this->validate($request);

        $this->hit($this->limit());

        $passwordDriver = $validated->parseNullableString('guard') ?? $this->getDefaultPasswordDriver();

        $result = (new PasswordResetService())->reset(
            $passwordDriver,
            $this->getUserProviderForPasswordDriver($passwordDriver),
            $validated->assertString('email'),
            $validated->assertString('token'),
            $validated->assertString('password'),
        );
        if ($result === PasswordBroker::INVALID_USER) {
            $request->thrower()
                ->error('email', PasswordBroker::INVALID_USER)
                ->throw();
        }
        if ($result === PasswordBroker::INVALID_TOKEN) {
            $request->thrower()
                ->error('token', PasswordBroker::INVALID_TOKEN)
                ->throw();
        }

        $user = Typer::assertInstance($result, BaseUser::class);
        Resolver::resolveDatabaseTokenGuard($user->getTable())->login($user);

        return $user->meResource()->response();
    }

    /**
     * Validate the incoming request.
     */
    protected function validate(ApiFormRequest $request): Parser
    {
        $authValidity = AuthValidity::inject();

        return $request->builder()
            ->rules([
                'token' => $authValidity->passwordResetToken()->required(),
                'email' => $authValidity->email()->required(),
                'password' => $authValidity->password()->required(),
            ])
            ->guard(GuardEnum::values())
            ->jsonApi()
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
     * Get the user provider for the given password driver.
     */
    protected function getUserProviderForPasswordDriver(string $passwordDriver): string
    {
        $config = Config::inject();

        $defaultUserProvider = $config->assertString('auth.defaults.provider');

        return $config->assertNullableString('auth.passwords.' . $passwordDriver . '.provider') ?? $defaultUserProvider;
    }
}
