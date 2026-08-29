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

class StatementUpdateController
{
    /**
     * Persist the daily amounts for the given statement.
     *
     * The statement is resolved through the scope user (admin or parent)
     * so limited users can update statements owned by their admin. A limited
     * user may only update statements attached to their assigned store.
     */
    public function __invoke(Request $request, ManageStatements $operation): RedirectResponse
    {
        $user = User::mustAuth();
        $statement = $operation->update(
            $user,
            Typer::parseInt($request->route('statement')),
            Typer::assertStringKeyArray($request->all()),
        );

        Inertia::flash('success', \__('Statement saved.'));

        return Resolver::resolveRedirector()->route('statements.index', [
            'store_id' => $statement->getStoreId(),
            'year' => $statement->getYear(),
            'month' => $statement->getMonth(),
        ]);
    }
}
