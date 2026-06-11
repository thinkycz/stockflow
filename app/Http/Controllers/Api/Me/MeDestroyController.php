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

class MeDestroyController extends AutomaticController
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

        Resolver::resolveDatabaseTokenGuard($guard)->logout();

        $user->delete();

        return Resolver::resolveResponseFactory()->noContent();
    }

    /**
     * Validate the incoming request.
     */
    protected function validate(ApiFormRequest $request): Parser
    {
        return $request->builder()
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
