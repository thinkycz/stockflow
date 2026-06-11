<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Enums\GuardEnum;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Http\ApiFormRequest;
use Thinkycz\LaravelCore\Models\BaseUser;
use Thinkycz\LaravelCore\Routing\AutomaticController;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Parser;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Validation\AuthValidity;

class LoginController extends AutomaticController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ApiFormRequest $request): SymfonyResponse
    {
        $validated = $this->validate($request);

        $this->hit($this->limit());

        $password = $validated->assertString('password');

        $credentials = [
            'email' => $validated->assertString('email'),
        ];

        $guard = $validated->parseNullableString('guard') ?? $this->getDefaultGuard();

        $user = Resolver::resolveEloquentUserProvider($guard)->retrieveByCredentials($credentials);

        if ($user instanceof BaseUser === false) {
            $request->thrower()
                ->errors(\array_keys($credentials), 'auth.failed')
                ->throw();
        }

        $passwordMatches = Resolver::resolveHasher()->check($password, $user->getAuthPassword());

        if ($passwordMatches === false) {
            $request->thrower()
                ->error('password', 'auth.password')
                ->throw();
        }

        Resolver::resolveDatabaseTokenGuard($guard)->login($user);

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
                'email' => $authValidity->email()->required(),
                'password' => $authValidity->password()->required(),
            ])
            ->guard(GuardEnum::values())
            ->jsonApi()
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
