<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Concerns;

use App\Models\Store;
use App\Models\User;

trait ResolvesDefaultStore
{
    /**
     * Pick the first owned store as the default selection, preferring
     * non-warehouse retail stores when one is available. Limited users
     * are pinned to their assigned store.
     *
     * @param array<int, Store> $stores
     */
    protected function resolveDefaultStore(array $stores, User|null $user = null): Store|null
    {
        $user ??= User::auth();

        if ($user !== null && !$user->isAdmin()) {
            $assignedId = $user->getAssignedStoreId();

            if ($assignedId !== null) {
                foreach ($stores as $store) {
                    if ($assignedId === $store->getKey()) {
                        return $store;
                    }
                }
            }

            return $stores[0] ?? null;
        }

        foreach ($stores as $store) {
            if (!$store->isWarehouse()) {
                return $store;
            }
        }

        return $stores[0] ?? null;
    }
}
