<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Auth\EmailVerificationConfirmController;
use App\Http\Controllers\Web\Auth\ForgotPasswordController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Auth\LogoutController;
use App\Http\Controllers\Web\Auth\ResetPasswordController;
use App\Http\Controllers\Web\Auth\VerifyEmailController;
use App\Http\Controllers\Web\Dashboard\DashboardController;
use App\Http\Controllers\Web\InventoryCount\InventoryCountHistoryController;
use App\Http\Controllers\Web\InventoryCount\InventoryCountIndexController;
use App\Http\Controllers\Web\InventoryCount\InventoryCountUpdateController;
use App\Http\Controllers\Web\Item\ItemCreateController;
use App\Http\Controllers\Web\Item\ItemDestroyController;
use App\Http\Controllers\Web\Item\ItemEditController;
use App\Http\Controllers\Web\Item\ItemIndexController;
use App\Http\Controllers\Web\Item\ItemSearchController;
use App\Http\Controllers\Web\Item\ItemShowController;
use App\Http\Controllers\Web\Report\ReportController;
use App\Http\Controllers\Web\Report\StatisticsController;
use App\Http\Controllers\Web\Settings\SettingsController;
use App\Http\Controllers\Web\Statement\StatementClearController;
use App\Http\Controllers\Web\Statement\StatementIndexController;
use App\Http\Controllers\Web\Statement\StatementUpdateController;
use App\Http\Controllers\Web\StockMovement\StockMovementCreateController;
use App\Http\Controllers\Web\StockMovement\StockMovementDestroyController;
use App\Http\Controllers\Web\StockMovement\StockMovementIndexController;
use App\Http\Controllers\Web\StockMovement\StockMovementShowController;
use App\Http\Controllers\Web\Store\StoreCreateController;
use App\Http\Controllers\Web\Store\StoreDestroyController;
use App\Http\Controllers\Web\Store\StoreEditController;
use App\Http\Controllers\Web\Store\StoreIndexController;
use App\Http\Controllers\Web\Store\StoreShowController;
use App\Http\Controllers\Web\User\UserCreateController;
use App\Http\Controllers\Web\User\UserDestroyController;
use App\Http\Controllers\Web\User\UserEditController;
use App\Http\Controllers\Web\User\UserIndexController;
use App\Http\Middleware\EnsureInertiaUserIsAuthenticated;
use App\Models\User;
use Illuminate\Routing\Router;
use Thinkycz\LaravelCore\Support\Resolver;

Resolver::resolveRouteRegistrar()->get('/', static function () {
    if (User::auth() instanceof User) {
        return Resolver::resolveRedirector()->route('dashboard');
    }

    return Resolver::resolveRedirector()->route('login.show');
})->name('home');

Resolver::resolveRouteRegistrar()
    ->middleware('guest:users')
    ->group(static function (Router $router): void {
        $router->get('login', [LoginController::class, 'create'])->name('login.show');
        $router->post('login', [LoginController::class, 'store'])->name('login.store');
        $router->get('forgot-password', [ForgotPasswordController::class, 'create'])->name('forgot-password.show');
        $router->post('forgot-password', [ForgotPasswordController::class, 'store'])->name('forgot-password.store');
        $router->get('reset-password', [ResetPasswordController::class, 'create'])->name('reset-password.show');
        $router->post('reset-password', [ResetPasswordController::class, 'store'])->name('reset-password.store');
    });

Resolver::resolveRouteRegistrar()->get('email/verify', EmailVerificationConfirmController::class)->name('email.verify');

Resolver::resolveRouteRegistrar()
    ->middleware(EnsureInertiaUserIsAuthenticated::class)
    ->group(static function (Router $router): void {
        $router->post('logout', LogoutController::class)->name('logout');
        $router->get('dashboard', DashboardController::class)->name('dashboard');

        // Statements (admin + limited)
        $router->get('statements', StatementIndexController::class)->name('statements.index');
        $router->put('statements/{statement}', StatementUpdateController::class)->whereNumber('statement')->name('statements.update');
        $router->post('statements/{statement}/clear', StatementClearController::class)->whereNumber('statement')->name('statements.clear');

        // Inventory counts (admin + limited)
        $router->get('inventory-counts', InventoryCountIndexController::class)->name('inventory-counts.index');
        $router->post('inventory-counts', InventoryCountUpdateController::class)->name('inventory-counts.update');
        $router->get('inventory-counts/history', InventoryCountHistoryController::class)->name('inventory-counts.history');

        // Settings
        $router->get('verify-email', [VerifyEmailController::class, 'create'])->name('verify-email.show');
        $router->post('verify-email', [VerifyEmailController::class, 'store'])->name('verify-email.store');
        $router->get('settings', [SettingsController::class, 'edit'])->name('settings.show');
        $router->post('settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile.update');
        $router->post('settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password.update');
    });

Resolver::resolveRouteRegistrar()
    ->middleware([EnsureInertiaUserIsAuthenticated::class, 'admin'])
    ->group(static function (Router $router): void {
        // Items
        $router->get('items', ItemIndexController::class)->name('items.index');
        $router->get('items/create', [ItemCreateController::class, 'create'])->name('items.create');
        $router->post('items', [ItemCreateController::class, 'store'])->name('items.store');
        $router->get('items/search', ItemSearchController::class)->name('items.search');
        $router->get('items/{item}', ItemShowController::class)->whereNumber('item')->name('items.show');
        $router->get('items/{item}/edit', [ItemEditController::class, 'edit'])->whereNumber('item')->name('items.edit');
        $router->put('items/{item}', [ItemEditController::class, 'update'])->whereNumber('item')->name('items.update');
        $router->delete('items/{item}', ItemDestroyController::class)->whereNumber('item')->name('items.destroy');

        // Stores
        $router->get('stores', StoreIndexController::class)->name('stores.index');
        $router->get('stores/create', [StoreCreateController::class, 'create'])->name('stores.create');
        $router->post('stores', [StoreCreateController::class, 'store'])->name('stores.store');
        $router->get('stores/{store}', StoreShowController::class)->whereNumber('store')->name('stores.show');
        $router->get('stores/{store}/edit', [StoreEditController::class, 'edit'])->whereNumber('store')->name('stores.edit');
        $router->put('stores/{store}', [StoreEditController::class, 'update'])->whereNumber('store')->name('stores.update');
        $router->delete('stores/{store}', StoreDestroyController::class)->whereNumber('store')->name('stores.destroy');

        // Stock movements
        $router->get('stock-movements', StockMovementIndexController::class)->name('stock-movements.index');
        $router->get('stock-movements/create', [StockMovementCreateController::class, 'create'])->name('stock-movements.create');
        $router->post('stock-movements', [StockMovementCreateController::class, 'store'])->name('stock-movements.store');
        $router->get('stock-movements/{stockMovement}', StockMovementShowController::class)->whereNumber('stockMovement')->name('stock-movements.show');
        $router->delete('stock-movements/{stockMovement}', StockMovementDestroyController::class)->whereNumber('stockMovement')->name('stock-movements.destroy');

        // Reports
        $router->get('reports', ReportController::class)->name('reports.index');
        $router->get('reports/statistics', StatisticsController::class)->name('reports.statistics');

        // Users
        $router->get('users', UserIndexController::class)->name('users.index');
        $router->get('users/create', [UserCreateController::class, 'create'])->name('users.create');
        $router->post('users', [UserCreateController::class, 'store'])->name('users.store');
        $router->get('users/{user}/edit', [UserEditController::class, 'edit'])->whereNumber('user')->name('users.edit');
        $router->put('users/{user}', [UserEditController::class, 'update'])->whereNumber('user')->name('users.update');
        $router->delete('users/{user}', UserDestroyController::class)->whereNumber('user')->name('users.destroy');
    });
