<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Statement;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\StatementValidity;
use App\Models\Statement;
use App\Models\User;
use App\Services\StatementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class StatementUpdateController
{
    use ValidatesWebRequests;

    /**
     * Persist the daily amounts for the given statement.
     *
     * The statement is resolved through the scope user (admin or parent)
     * so limited users can update statements owned by their admin. A limited
     * user may only update statements attached to their assigned store.
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

        $this->ensureCanEdit($user, $statement);

        $validity = StatementValidity::inject($statement->getUserId());

        $validated = $this->validateRequest($request, [
            'days' => $validity->days()->required()->toArray(),
            'days.*.date' => $validity->dayDate()->required()->toArray(),
            'days.*.cash' => $validity->amount()->required()->toArray(),
            'days.*.card' => $validity->amount()->required()->toArray(),
            'days.*.wolt' => $validity->amount()->required()->toArray(),
            'days.*.bolt' => $validity->amount()->required()->toArray(),
            'days.*.bolt_cash' => $validity->amount()->required()->toArray(),
            'days.*.foodora' => $validity->amount()->required()->toArray(),
        ]);

        /** @var array<int, array<string, mixed>> $rows */
        $rows = Typer::assertArray($validated->assertArray('days'));
        $service->updateDays($statement, $rows, $user);

        Inertia::flash('success', \__('Statement saved.'));

        return Resolver::resolveRedirector()->route('statements.index', [
            'store_id' => $statement->getStoreId(),
            'year' => $statement->getYear(),
            'month' => $statement->getMonth(),
        ]);
    }

    /**
     * Ensure the user can edit the given statement. Limited users can only
     * edit statements attached to their assigned store.
     */
    private function ensureCanEdit(User $user, Statement $statement): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $assignedStoreId = $user->getAssignedStoreId();

        if ($assignedStoreId === null || $assignedStoreId !== $statement->getStoreId()) {
            \abort(403);
        }
    }
}
