<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Statement;

use App\Models\Statement;
use App\Models\User;
use App\Services\StatementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class StatementClearController
{
    /**
     * Reset all daily amounts of the given statement to zero.
     *
     * The statement is resolved through the scope user (admin or parent)
     * so limited users can clear statements owned by their admin. A limited
     * user may only clear statements attached to their assigned store.
     */
    public function __invoke(Request $request, StatementService $service): RedirectResponse
    {
        $user = User::mustAuth();
        $scopeUser = $user->resolveScopeUser();

        $statement = Statement::query()
            ->where('user_id', $scopeUser->getKey())
            ->whereKey(Typer::parseInt($request->route('statement')))
            ->first();

        if (!$statement instanceof Statement) {
            \abort(404);
        }

        if (!$user->isAdmin()) {
            $assignedStoreId = $user->getAssignedStoreId();

            if ($assignedStoreId === null || $assignedStoreId !== $statement->getStoreId()) {
                \abort(403);
            }
        }

        $service->clear($statement, $user);

        Inertia::flash('success', \__('Statement cleared.'));

        return Resolver::resolveRedirector()->route('statements.index', [
            'store_id' => $statement->getStoreId(),
            'year' => $statement->getYear(),
            'month' => $statement->getMonth(),
        ]);
    }
}
