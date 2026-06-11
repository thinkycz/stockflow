<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Me;

use App\Enums\GuardEnum;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Http\ApiFormRequest;
use Thinkycz\LaravelCore\Models\BaseUser;
use Thinkycz\LaravelCore\Routing\AutomaticController;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Parser;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;
use Thinkycz\LaravelCore\Validation\AuthValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class MeUpdateController extends AutomaticController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ApiFormRequest $request): SymfonyResponse
    {
        $validated = $this->validate($request);

        $this->hit($this->limit());

        $guard = $validated->parseNullableString('guard') ?? $this->getDefaultGuard();

        $user = Resolver::resolveAuthManager()->guard($guard)->user();

        if ($user instanceof BaseUser === false) {
            throw new AuthenticationException();
        }

        $data = Typer::assertStringKeyArray($validated->except([
            'fields',
            'guard',
            'include',
        ]));

        $user->update($data);

        return $user->meResource()->response();
    }

    /**
     * Validate the incoming request.
     */
    protected function validate(ApiFormRequest $request): Parser
    {
        $authValidity = AuthValidity::inject();

        $guard = $request->builder()
            ->rules([
                'email' => Validity::make()->unsafe(),
                'locale' => Validity::make()->unsafe(),
            ])
            ->guard(GuardEnum::values())
            ->jsonApi()
            ->validate()
            ->parseNullableString('guard') ?? GuardEnum::USERS->value;

        $user = Resolver::resolveAuthManager()->guard($guard)->user();

        if ($user instanceof BaseUser === false) {
            throw new AuthenticationException();
        }

        return $request->builder()
            ->rules([
                'email' => $authValidity->email()->unique($guard, 'email', $user->getKey())->nullable()->filled(),
                'locale' => $authValidity->locale()->nullable()->filled(),
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
