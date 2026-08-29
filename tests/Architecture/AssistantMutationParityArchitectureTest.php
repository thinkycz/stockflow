<?php

declare(strict_types=1);

use App\Ai\AssistantToolCatalog;
use App\Http\Middleware\EnsureInertiaUserIsAuthenticated;
use Illuminate\Routing\Route;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

\test('every authenticated mutation route has an assistant operation or documented classification', function (): void {
    $matrix = Typer::assertStringKeyArray(Config::inject()->assertArray('assistant_route_parity'));
    $supported = Typer::assertStringKeyArray(Typer::assertArray($matrix['supported'] ?? null));
    $readOnly = Typer::assertStringKeyArray(Typer::assertArray($matrix['semantically_read_only'] ?? null));
    $excluded = Typer::assertStringKeyArray(Typer::assertArray($matrix['excluded'] ?? null));
    $classified = [...\array_keys($supported), ...\array_keys($readOnly), ...\array_keys($excluded)];
    $mutationRoutes = [];

    foreach (Resolver::resolveRouter()->getRoutes() as $route) {
        if (!$route instanceof Route || \array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $route->methods()) === []) {
            continue;
        }

        if (!\in_array(EnsureInertiaUserIsAuthenticated::class, $route->gatherMiddleware(), true)) {
            continue;
        }

        $mutationRoutes[] = Typer::assertString($route->getName());
    }

    \sort($mutationRoutes);
    $missing = \array_values(\array_diff($mutationRoutes, $classified));

    \expect($missing)->toBe([], 'Unclassified authenticated mutations: ' . \implode(', ', $missing));
    \expect(\array_intersect(\array_keys($supported), \array_keys($readOnly)))->toBe([]);
    \expect(\array_intersect(\array_keys($supported), \array_keys($excluded)))->toBe([]);
    \expect(\array_intersect(\array_keys($readOnly), \array_keys($excluded)))->toBe([]);

    $capabilities = Resolver::resolve(AssistantToolCatalog::class)->capabilities();
    $mappedCapabilities = [];

    foreach ($supported as $routeName => $definitionValue) {
        $definition = Typer::assertStringKeyArray(Typer::assertArray($definitionValue));
        $tool = Typer::assertString($definition['tool'] ?? null);
        $action = Typer::assertString($definition['action'] ?? null);

        \expect($capabilities)->toHaveKey($tool)
            ->and($capabilities[$tool])->toContain($action);
        $mappedCapabilities[$tool . ':' . $action] = $routeName;
    }

    foreach ($capabilities as $tool => $actions) {
        foreach ($actions as $action) {
            \expect(\array_key_exists($tool . ':' . $action, $mappedCapabilities))->toBeTrue(
                "Native writer action {$tool}:{$action} has no route mapping or documented assistant-only classification.",
            );
        }
    }
});
