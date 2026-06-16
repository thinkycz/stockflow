<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Statement;

use App\Models\Statement;
use App\Services\StatementService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

class StatementClearController
{
    /**
     * Reset all daily amounts of the given statement to zero.
     */
    public function __invoke(Statement $statement, StatementService $service): RedirectResponse
    {
        $service->clear($statement);

        Inertia::flash('success', \__('Statement cleared.'));

        return Resolver::resolveRedirector()->route('statements.index', [
            'store_id' => $statement->getStoreId(),
            'year' => $statement->getYear(),
            'month' => $statement->getMonth(),
        ]);
    }
}
