<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\EmailVerification\EmailVerificationResendController;
use App\Http\Controllers\Api\EmailVerification\EmailVerificationVerifyController;
use App\Http\Controllers\Api\Me\MeDestroyController;
use App\Http\Controllers\Api\Me\MeShowController;
use App\Http\Controllers\Api\Me\MeUpdateController;
use App\Http\Controllers\Api\Password\PasswordForgotController;
use App\Http\Controllers\Api\Password\PasswordResetController;
use App\Http\Controllers\Api\Password\PasswordUpdateController;
use Illuminate\Routing\Router;
use Thinkycz\LaravelCore\Support\Resolver;

Resolver::resolveRouteRegistrar()
    ->prefix('v1/me')
    ->group(static function (Router $router): void {
        $router->get('show', MeShowController::class)->name('v1.me.show');
        $router->post('update', MeUpdateController::class)->name('v1.me.update');
        $router->post('destroy', MeDestroyController::class)->name('v1.me.destroy');
    });

Resolver::resolveRouteRegistrar()
    ->prefix('v1/auth')
    ->group(static function (Router $router): void {
        $router->post('login', LoginController::class)->name('login');
        $router->post('register', RegisterController::class)->name('v1.auth.register');
        $router->post('logout', LogoutController::class)->name('v1.auth.logout');
    });

Resolver::resolveRouteRegistrar()
    ->prefix('v1/email_verification')
    ->group(static function (Router $router): void {
        $router->post('verify', EmailVerificationVerifyController::class)->name('v1.email_verification.verify');
        $router->post('resend', EmailVerificationResendController::class)->name('v1.email_verification.resend');
    });

Resolver::resolveRouteRegistrar()
    ->prefix('v1/password')
    ->group(static function (Router $router): void {
        $router->post('forgot', PasswordForgotController::class)->name('v1.password.forgot');
        $router->post('reset', PasswordResetController::class)->name('v1.password.reset');
        $router->post('update', PasswordUpdateController::class)->name('v1.password.update');
    });
