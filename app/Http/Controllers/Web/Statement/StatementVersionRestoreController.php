<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Statement;

use App\Domain\Statements\ManageStatements;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

class StatementVersionRestoreController
{
    /**
     * Restore the statement's daily amounts from the given version.
     *
     * A backup snapshot of the current state is taken before the data
     * is overwritten, so the user can revert the restore itself.
     * Versions are owned by the admin; a limited user only gets access
     * when the version is owned by their parent (admin) and the
     * underlying statement belongs to their assigned store.
     */
    public function __invoke(int $version, ManageStatements $operation): RedirectResponse
    {
        $statement = $operation->restoreVersion(User::mustAuth(), $version);

        Inertia::flash('success', \__('Statement restored from version.'));

        return Resolver::resolveRedirector()->route('statements.index', [
            'store_id' => $statement->getStoreId(),
            'year' => $statement->getYear(),
            'month' => $statement->getMonth(),
        ]);
    }
}
