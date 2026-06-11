<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Support\Resolver;

class LogoutController
{
    /**
     * Log the current user out.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        Resolver::resolveDatabaseTokenGuard('users')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Resolver::resolveRedirector()->to('/login');
    }
}
