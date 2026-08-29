<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Statement;

use App\Models\User;
use App\Operations\Statements\ManageStatements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class StatementTodayUpdateController
{
    /**
     * Persist only the current Prague business day's statement amounts.
     */
    public function __invoke(Request $request, ManageStatements $operation): RedirectResponse
    {
        $operation->updateToday(
            User::mustAuth(),
            Typer::parseInt($request->route('statement')),
            Typer::assertStringKeyArray($request->all()),
        );

        Inertia::flash('success', \__('Statement saved.'));

        return Resolver::resolveRedirector()->back();
    }
}
