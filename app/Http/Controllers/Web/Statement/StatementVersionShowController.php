<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Statement;

use App\Models\StatementVersion;
use App\Models\StatementVersionDay;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class StatementVersionShowController
{
    /**
     * Render the read-only detail of a single statement version.
     *
     * Versions are owned by the admin; a limited user only gets access
     * when the version is owned by their parent (admin) and the
     * underlying statement belongs to their assigned store.
     */
    public function __invoke(int $version): Response
    {
        $user = User::mustAuth();
        $scopeUser = $user->resolveScopeUser();

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

        $store = $statement->getStore();
        $creator = $versionModel->creator()->first();

        $rows = $versionModel->days()->orderBy('date')->get()->map(static fn(StatementVersionDay $day): array => [
            'date' => $day->getDate(),
            'cash' => $day->getCash(),
            'card' => $day->getCard(),
            'wolt' => $day->getWolt(),
            'bolt' => $day->getBolt(),
            'bolt_cash' => $day->getBoltCash(),
            'foodora' => $day->getFoodora(),
            'total' => $day->getTotal(),
            'cash_checked' => $day->getCashChecked(),
        ])->all();

        return Inertia::render('statements/Version', [
            'version' => [
                'id' => $versionModel->getKey(),
                'snapshot_at' => $versionModel->getSnapshotAt()->toIso8601String(),
                'note' => $versionModel->getNote(),
                'created_by' => $versionModel->getCreatedBy(),
                'created_by_email' => $creator?->getEmail(),
            ],
            'statement' => [
                'id' => $statement->getKey(),
                'store_id' => $store->getKey(),
                'store_name' => $store->getName(),
                'year' => $statement->getYear(),
                'month' => $statement->getMonth(),
            ],
            'rows' => $rows,
            'is_admin' => $user->isAdmin(),
        ]);
    }
}
