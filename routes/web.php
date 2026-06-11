<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Auth\EmailVerificationConfirmController;
use App\Http\Controllers\Web\Auth\ForgotPasswordController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Auth\LogoutController;
use App\Http\Controllers\Web\Auth\RegisterController;
use App\Http\Controllers\Web\Auth\ResetPasswordController;
use App\Http\Controllers\Web\Auth\VerifyEmailController;
use App\Http\Controllers\Web\Dashboard\DashboardController;
use App\Http\Controllers\Web\Item\ItemCreateController;
use App\Http\Controllers\Web\Item\ItemDestroyController;
use App\Http\Controllers\Web\Item\ItemEditController;
use App\Http\Controllers\Web\Item\ItemIndexController;
use App\Http\Controllers\Web\Item\ItemShowController;
use App\Http\Controllers\Web\Report\ReportController;
use App\Http\Controllers\Web\Settings\SettingsController;
use App\Http\Controllers\Web\StockMovement\StockMovementCreateController;
use App\Http\Controllers\Web\StockMovement\StockMovementIndexController;
use App\Http\Controllers\Web\StockMovement\StockMovementShowController;
use App\Http\Controllers\Web\Store\StoreCreateController;
use App\Http\Controllers\Web\Store\StoreEditController;
use App\Http\Controllers\Web\Store\StoreIndexController;
use App\Http\Controllers\Web\Store\StoreShowController;
use App\Http\Middleware\EnsureInertiaUserIsAuthenticated;
use App\Models\User;
use Illuminate\Routing\Router;
use Thinkycz\LaravelCore\Support\Resolver;

Resolver::resolveRouteRegistrar()->get('/', static function () {
    if (User::auth() instanceof User) {
        return Resolver::resolveRedirector()->to('/dashboard');
    }

    return Resolver::resolveRedirector()->to('/login');
});

Resolver::resolveRouteRegistrar()
    ->middleware('guest:users')
    ->group(static function (Router $router): void {
        $router->get('login', [LoginController::class, 'create']);
        $router->post('login', [LoginController::class, 'store']);
        $router->get('register', [RegisterController::class, 'create']);
        $router->post('register', [RegisterController::class, 'store']);
        $router->get('forgot-password', [ForgotPasswordController::class, 'create']);
        $router->post('forgot-password', [ForgotPasswordController::class, 'store']);
        $router->get('reset-password', [ResetPasswordController::class, 'create']);
        $router->post('reset-password', [ResetPasswordController::class, 'store']);
    });

Resolver::resolveRouteRegistrar()->get('email/verify', EmailVerificationConfirmController::class);

Resolver::resolveRouteRegistrar()
    ->middleware(EnsureInertiaUserIsAuthenticated::class)
    ->group(static function (Router $router): void {
        $router->post('logout', LogoutController::class);
        $router->get('dashboard', DashboardController::class);

        // Items
        $router->get('items', ItemIndexController::class);
        $router->get('items/create', [ItemCreateController::class, 'create']);
        $router->post('items', [ItemCreateController::class, 'store']);
        $router->get('items/{item}', ItemShowController::class)->whereNumber('item');
        $router->get('items/{item}/edit', [ItemEditController::class, 'edit'])->whereNumber('item');
        $router->put('items/{item}', [ItemEditController::class, 'update'])->whereNumber('item');
        $router->delete('items/{item}', ItemDestroyController::class)->whereNumber('item');

        // Stores
        $router->get('stores', StoreIndexController::class);
        $router->get('stores/create', [StoreCreateController::class, 'create']);
        $router->post('stores', [StoreCreateController::class, 'store']);
        $router->get('stores/{store}', StoreShowController::class)->whereNumber('store');
        $router->get('stores/{store}/edit', [StoreEditController::class, 'edit'])->whereNumber('store');
        $router->put('stores/{store}', [StoreEditController::class, 'update'])->whereNumber('store');

        // Stock movements
        $router->get('stock-movements', StockMovementIndexController::class);
        $router->get('stock-movements/create', [StockMovementCreateController::class, 'create']);
        $router->post('stock-movements', [StockMovementCreateController::class, 'store']);
        $router->get('stock-movements/{stockMovement}', StockMovementShowController::class)->whereNumber('stockMovement');

        // Reports
        $router->get('reports', ReportController::class);

        // Settings
        $router->get('verify-email', [VerifyEmailController::class, 'create']);
        $router->post('verify-email', [VerifyEmailController::class, 'store']);
        $router->get('settings', [SettingsController::class, 'edit']);
        $router->post('settings/profile', [SettingsController::class, 'updateProfile']);
        $router->post('settings/password', [SettingsController::class, 'updatePassword']);
    });
