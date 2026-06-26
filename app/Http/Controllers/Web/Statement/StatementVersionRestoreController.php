<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Statement;

use App\Models\StatementVersion;
use App\Models\User;
use App\Services\StatementService;
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
    public function __invoke(int $version, StatementService $service): RedirectResponse
    {
        $user = User::mustAuth();
        $scopeUser = $this->resolveScopeUser($user);

        $versionModel = StatementVersion::query()
            ->where('user_id', $scopeUser->getKey())
            ->whereKey($version)
            ->first();

        if (!$versionModel instanceof StatementVersion) {
            \abort(404);
        }

        $statement = $versionModel->getStatement();

        if (!$user->isAdmin()) {
            $assignedStoreId = $user->getAssignedStoreId();

            if ($assignedStoreId === null || $assignedStoreId !== $statement->getStoreId()) {
                \abort(403);
            }
        }

        $service->restoreVersion($versionModel, $user);

        Inertia::flash('success', \__('Statement restored from version.'));

        return Resolver::resolveRedirector()->route('statements.index', [
            'store_id' => $statement->getStoreId(),
            'year' => $statement->getYear(),
            'month' => $statement->getMonth(),
        ]);
    }

    /**
     * The owner used to scope version lookups. Limited users resolve
     * to their parent (admin) so they can browse the admin's versions.
     */
    private function resolveScopeUser(User $user): User
    {
        if ($user->isAdmin()) {
            return $user;
        }

        $parentId = $user->getParentUserId();

        if ($parentId !== null) {
            $parent = User::query()->whereKey($parentId)->first();

            if ($parent instanceof User) {
                return $parent;
            }
        }

        return $user;
    }
}
