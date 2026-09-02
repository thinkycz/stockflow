<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Web\Concerns\ThrottlesWebRequests;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Services\PasswordResetRequestService;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Validation\AuthValidity;

class ForgotPasswordController
{
    use ThrottlesWebRequests;
    use ValidatesWebRequests;

    /**
     * Show the forgot password page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/ForgotPassword');
    }

    /**
     * Send or apply the reset flow configured by the core package.
     */
    public function store(Request $request, PasswordResetRequestService $passwordReset): Response
    {
        $authValidity = AuthValidity::inject();

        $validated = $this->validateRequest($request, [
            'email' => $authValidity->email()->required()->toArray(),
        ]);

        $this->hit($this->limit());

        $passwordReset->send('users', $validated->assertString('email'));

        Inertia::flash('success', \__(PasswordBroker::RESET_LINK_SENT));

        return Inertia::render('auth/ForgotPassword');
    }
}
