<?php

declare(strict_types=1);

use App\Ai\AssistantToolCatalog;
use App\Http\Middleware\EnsureInertiaUserIsAuthenticated;
use Illuminate\Routing\Route;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

\test('every authenticated read route has a native reader dataset or documented exclusion', function (): void {
    $matrix = Typer::assertStringKeyArray(Config::inject()->assertArray('assistant_read_route_parity'));
    $supported = Typer::assertStringKeyArray(Typer::assertArray($matrix['supported'] ?? null));
    $assistantOnly = Typer::assertStringKeyArray(Typer::assertArray($matrix['assistant_only'] ?? null));
    $excluded = Typer::assertStringKeyArray(Typer::assertArray($matrix['excluded'] ?? null));
    $classified = [...\array_keys($supported), ...\array_keys($excluded)];
    $readRoutes = [];

    foreach (Resolver::resolveRouter()->getRoutes() as $route) {
        if (!$route instanceof Route || !\in_array('GET', $route->methods(), true)) {
            continue;
        }
        if (!\in_array(EnsureInertiaUserIsAuthenticated::class, $route->gatherMiddleware(), true)) {
            continue;
        }
        $readRoutes[] = Typer::assertString($route->getName());
    }

    \sort($readRoutes);
    \expect(\array_values(\array_diff($readRoutes, $classified)))->toBe([])
        ->and(\array_intersect(\array_keys($supported), \array_keys($excluded)))->toBe([]);

    $capabilities = Resolver::resolve(AssistantToolCatalog::class)->readCapabilities();
    foreach ($supported as $definitionValue) {
        $definition = Typer::assertStringKeyArray(Typer::assertArray($definitionValue));
        $tool = Typer::assertString($definition['tool'] ?? null);
        $dataset = Typer::assertString($definition['dataset'] ?? null);
        \expect($capabilities)->toHaveKey($tool)
            ->and($capabilities[$tool])->toContain($dataset);
    }

    foreach ($capabilities as $tool => $datasets) {
        foreach ($datasets as $dataset) {
            $mapped = \array_filter($supported, static fn(mixed $definition): bool => \is_array($definition) && ($definition['tool'] ?? null) === $tool && ($definition['dataset'] ?? null) === $dataset);
            \expect($mapped !== [] || \array_key_exists("{$tool}:{$dataset}", $assistantOnly))->toBeTrue(
                "Reader dataset {$tool}:{$dataset} has no human route mapping or documented assistant-only justification.",
            );
        }
    }
});
