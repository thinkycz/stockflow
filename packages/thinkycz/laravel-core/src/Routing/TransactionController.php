<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Routing;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class TransactionController extends Controller
{
    /**
     * @inheritDoc
     */
    public function callAction(mixed $method, mixed $parameters): SymfonyResponse
    {
        return Typer::assertInstance(Resolver::resolveDatabaseManager()->connection()->transaction(fn(): SymfonyResponse => parent::callAction($method, $parameters)), SymfonyResponse::class);
    }
}
