<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureInertiaUserIsAuthenticated;
use Illuminate\Routing\Router;
use Thinkycz\LaravelCore\Support\Resolver;

Resolver::resolveRouteRegistrar()->get('/', static function () {
    if (App\Models\User::auth() instanceof App\Models\User) {
        return Resolver::resolveRedirector()->to('/dashboard');
    }

    return Resolver::resolveRedirector()->to('/login');
});

Resolver::resolveRouteRegistrar()
    ->middleware('guest:users')
    ->group(static function (Router $router): void {
        $router->get('login', [App\Http\Controllers\Web\Auth\LoginController::class, 'create']);
        $router->post('login', [App\Http\Controllers\Web\Auth\LoginController::class, 'store']);
        $router->get('register', [App\Http\Controllers\Web\Auth\RegisterController::class, 'create']);
        $router->post('register', [App\Http\Controllers\Web\Auth\RegisterController::class, 'store']);
        $router->get('forgot-password', [App\Http\Controllers\Web\Auth\ForgotPasswordController::class, 'create']);
        $router->post('forgot-password', [App\Http\Controllers\Web\Auth\ForgotPasswordController::class, 'store']);
        $router->get('reset-password', [App\Http\Controllers\Web\Auth\ResetPasswordController::class, 'create']);
        $router->post('reset-password', [App\Http\Controllers\Web\Auth\ResetPasswordController::class, 'store']);
    });

Resolver::resolveRouteRegistrar()->get('email/verify', App\Http\Controllers\Web\Auth\EmailVerificationConfirmController::class);

Resolver::resolveRouteRegistrar()
    ->middleware(EnsureInertiaUserIsAuthenticated::class)
    ->group(static function (Router $router): void {
        $router->post('logout', App\Http\Controllers\Web\Auth\LogoutController::class);
        $router->get('dashboard', App\Http\Controllers\Web\Dashboard\DashboardController::class);

        // Items
        $router->get('items', App\Http\Controllers\Web\Item\ItemIndexController::class);
        $router->get('items/create', [App\Http\Controllers\Web\Item\ItemCreateController::class, 'create']);
        $router->post('items', [App\Http\Controllers\Web\Item\ItemCreateController::class, 'store']);
        $router->get('items/{item}', App\Http\Controllers\Web\Item\ItemShowController::class)->whereNumber('item');
        $router->get('items/{item}/edit', [App\Http\Controllers\Web\Item\ItemEditController::class, 'edit'])->whereNumber('item');
        $router->put('items/{item}', [App\Http\Controllers\Web\Item\ItemEditController::class, 'update'])->whereNumber('item');
        $router->delete('items/{item}', App\Http\Controllers\Web\Item\ItemDestroyController::class)->whereNumber('item');

        // Stores
        $router->get('stores', App\Http\Controllers\Web\Store\StoreIndexController::class);
        $router->get('stores/create', [App\Http\Controllers\Web\Store\StoreCreateController::class, 'create']);
        $router->post('stores', [App\Http\Controllers\Web\Store\StoreCreateController::class, 'store']);
        $router->get('stores/{store}', App\Http\Controllers\Web\Store\StoreShowController::class)->whereNumber('store');
        $router->get('stores/{store}/edit', [App\Http\Controllers\Web\Store\StoreEditController::class, 'edit'])->whereNumber('store');
        $router->put('stores/{store}', [App\Http\Controllers\Web\Store\StoreEditController::class, 'update'])->whereNumber('store');

        // Stock movements
        $router->get('stock-movements', App\Http\Controllers\Web\StockMovement\StockMovementIndexController::class);
        $router->get('stock-movements/create', [App\Http\Controllers\Web\StockMovement\StockMovementCreateController::class, 'create']);
        $router->post('stock-movements', [App\Http\Controllers\Web\StockMovement\StockMovementCreateController::class, 'store']);
        $router->get('stock-movements/{stockMovement}', App\Http\Controllers\Web\StockMovement\StockMovementShowController::class)->whereNumber('stockMovement');

        // Reports
        $router->get('reports', App\Http\Controllers\Web\Report\ReportController::class);

        // Settings
        $router->get('verify-email', [App\Http\Controllers\Web\Auth\VerifyEmailController::class, 'create']);
        $router->post('verify-email', [App\Http\Controllers\Web\Auth\VerifyEmailController::class, 'store']);
        $router->get('settings', [App\Http\Controllers\Web\Settings\SettingsController::class, 'edit']);
        $router->post('settings/profile', [App\Http\Controllers\Web\Settings\SettingsController::class, 'updateProfile']);
        $router->post('settings/password', [App\Http\Controllers\Web\Settings\SettingsController::class, 'updatePassword']);
    });
