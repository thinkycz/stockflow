<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Routing;

use Symfony\Component\HttpFoundation\Response;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class AutomaticController extends Controller
{
    /**
     * @inheritDoc
     *
     * @param array<mixed> $parameters
     */
    public function callAction(mixed $method, mixed $parameters): Response
    {
        if (Resolver::resolveRequest()->isMethodSafe()) {
            return parent::callAction($method, $parameters);
        }

        return Typer::assertInstance(Resolver::resolveDatabaseManager()->connection()->transaction(fn(): Response => parent::callAction($method, $parameters)), Response::class);
    }
}
