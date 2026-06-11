<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use Thinkycz\LaravelCore\Support\Resolver;

Resolver::resolveRouteRegistrar()
    ->prefix('v1/me')
    ->group(static function (Router $router): void {
        $router->get('show', App\Http\Controllers\Api\Me\MeShowController::class);
        $router->post('update', App\Http\Controllers\Api\Me\MeUpdateController::class);
        $router->post('destroy', App\Http\Controllers\Api\Me\MeDestroyController::class);
    });

Resolver::resolveRouteRegistrar()
    ->prefix('v1/auth')
    ->group(static function (Router $router): void {
        $router->post('login', App\Http\Controllers\Api\Auth\LoginController::class)->name('login');
        $router->post('register', App\Http\Controllers\Api\Auth\RegisterController::class);
        $router->post('logout', App\Http\Controllers\Api\Auth\LogoutController::class);
    });

Resolver::resolveRouteRegistrar()
    ->prefix('v1/email_verification')
    ->group(static function (Router $router): void {
        $router->post('verify', App\Http\Controllers\Api\EmailVerification\EmailVerificationVerifyController::class);
        $router->post('resend', App\Http\Controllers\Api\EmailVerification\EmailVerificationResendController::class);
    });

Resolver::resolveRouteRegistrar()
    ->prefix('v1/password')
    ->group(static function (Router $router): void {
        $router->post('forgot', App\Http\Controllers\Api\Password\PasswordForgotController::class);
        $router->post('reset', App\Http\Controllers\Api\Password\PasswordResetController::class);
        $router->post('update', App\Http\Controllers\Api\Password\PasswordUpdateController::class);
    });
