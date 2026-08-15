<?php

declare(strict_types=1);

use Illuminate\Routing\Route;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

\test('all shared operational routes register their section middleware', function (): void {
    $expected = [
        'statements.index' => 'limited-section:statements',
        'statements.update' => 'limited-section:statements',
        'inventory-counts.index' => 'limited-section:inventory_counts',
        'inventory-counts.update' => 'limited-section:inventory_counts',
        'shifts.index' => 'limited-section:shifts',
        'shift-share-links.store' => 'limited-section:shifts',
        'attendance.index' => 'limited-section:attendance',
        'attendance.actions.store' => 'limited-section:attendance',
        'gift-vouchers.index' => 'limited-section:gift_vouchers',
        'gift-vouchers.lookup' => 'limited-section:gift_vouchers',
        'checklist-items.update' => 'limited-section:checklists',
        'recipes.index' => 'limited-section:recipes',
        'recipe-test-sessions.store' => 'limited-section:recipes',
        'stock-movements.create' => 'limited-stock-movement',
        'stock-movements.store' => 'limited-stock-movement',
        'items.search' => 'limited-stock-movement',
    ];

    foreach ($expected as $routeName => $middleware) {
        $route = Typer::assertInstance(
            Resolver::resolveRouter()->getRoutes()->getByName($routeName),
            Route::class,
        );

        \expect($route->gatherMiddleware())->toContain($middleware);
    }
});
