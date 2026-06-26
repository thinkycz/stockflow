<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Statement;

use App\Models\Statement;
use App\Models\User;
use App\Services\StatementService;
use Inertia\Inertia;
use Inertia\Response;

class StatementHistoryController
{
    /**
     * Page size hint required by the web index controller architecture test.
     *
     * The history is bounded by `StatementService::HISTORY_LIMIT`; pagination
     * is not exposed.
     */
    public const int TAKE = StatementService::HISTORY_LIMIT;

    /**
     * Render the version history page for a single statement.
     *
     * Statements are owned by the admin; a limited user only gets access
     * when the statement is owned by their parent (admin) and belongs to
     * their assigned store.
     */
    public function __invoke(int $statement, StatementService $service): Response
    {
        $user = User::mustAuth();
        $scopeUser = $this->resolveScopeUser($user);

        $statementModel = Statement::query()
            ->where('user_id', $scopeUser->getKey())
            ->whereKey($statement)
            ->first();

        if (!$statementModel instanceof Statement) {
            \abort(404);
        }

        if (!$user->isAdmin()) {
            $assignedStoreId = $user->getAssignedStoreId();

            if ($assignedStoreId === null || $assignedStoreId !== $statementModel->getStoreId()) {
                \abort(403);
            }
        }

        $store = $statementModel->getStore();

        return Inertia::render('statements/History', [
            'statement' => [
                'id' => $statementModel->getKey(),
                'store_id' => $store->getKey(),
                'store_name' => $store->getName(),
                'year' => $statementModel->getYear(),
                'month' => $statementModel->getMonth(),
            ],
            'rows' => $service->historyForStatement($statementModel, self::TAKE),
            'filters' => [
                'store_id' => $store->getKey(),
                'year' => $statementModel->getYear(),
                'month' => $statementModel->getMonth(),
            ],
            'is_admin' => $user->isAdmin(),
        ]);
    }

    /**
     * The owner used to scope statement lookups. Limited users resolve
     * to their parent (admin) so they can browse the admin's statements.
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
