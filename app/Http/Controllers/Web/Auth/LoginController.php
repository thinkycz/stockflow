<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Web\Concerns\ThrottlesWebRequests;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Models\BaseUser;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;
use Thinkycz\LaravelCore\Validation\AuthValidity;

class LoginController
{
    use ThrottlesWebRequests;
    use ValidatesWebRequests;

    /**
     * Show the login page.
     */
    public function create(): RedirectResponse|Response
    {
        if (User::auth() instanceof User) {
            return Resolver::resolveRedirector()->route('dashboard');
        }

        return Inertia::render('auth/Login');
    }

    /**
     * Authenticate the user.
     */
    public function store(Request $request): SymfonyResponse
    {
        $authValidity = AuthValidity::inject();

        $validated = $this->validateRequest($request, [
            'email' => $authValidity->email()->required()->toArray(),
            'password' => $authValidity->password()->required()->toArray(),
        ]);

        $this->hit($this->limit());

        $email = $validated->assertString('email');
        $password = $validated->assertString('password');

        $user = Resolver::resolveEloquentUserProvider('users')->retrieveByCredentials([
            'email' => $email,
        ]);

        $hasher = Resolver::resolveHasher();

        if ($user instanceof BaseUser === false) {
            $hasher->check($password, '$2y$10$' . \str_repeat('a', 53));

            Thrower::default()->message('email', Typer::assertString(\__('auth.failed')))->throw();
        }

        if (!$hasher->check($password, $user->getAuthPassword())) {
            Thrower::default()->message('password', Typer::assertString(\__('auth.password')))->throw();
        }

        Resolver::resolveDatabaseTokenGuard('users')->login($user);

        $request->session()->regenerate();

        return Resolver::resolveRedirector()->intended(\route('dashboard'));
    }
}
