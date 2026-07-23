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
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class StatementTodayUpdateController
{
    use ValidatesWebRequests;

    /**
     * Persist only the current Prague business day's statement amounts.
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

        $today = Carbon::now(StatementService::TIMEZONE);

        if ($today->year !== $statement->getYear() || $today->month !== $statement->getMonth()) {
            \abort(404);
        }

        $validity = StatementValidity::inject($statement->getUserId());
        $validated = $this->validateRequest($request, [
            'cash' => $validity->amount()->required()->toArray(),
            'card' => $validity->amount()->required()->toArray(),
            'wolt' => $validity->amount()->required()->toArray(),
            'bolt' => $validity->amount()->required()->toArray(),
            'bolt_cash' => $validity->amount()->required()->toArray(),
            'foodora' => $validity->amount()->required()->toArray(),
        ]);

        $service->updateDays($statement, [[
            'date' => $today->toDateString(),
            'cash' => $validated->parseFloat('cash'),
            'card' => $validated->parseFloat('card'),
            'wolt' => $validated->parseFloat('wolt'),
            'bolt' => $validated->parseFloat('bolt'),
            'bolt_cash' => $validated->parseFloat('bolt_cash'),
            'foodora' => $validated->parseFloat('foodora'),
        ]], $user);

        Inertia::flash('success', \__('Statement saved.'));

        return Resolver::resolveRedirector()->back();
    }

    /**
     * Ensure limited users only edit their assigned store.
     */
    private function ensureCanEdit(User $user, Statement $statement): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($user->getAssignedStoreId() !== $statement->getStoreId()) {
            \abort(403);
        }
    }
}
