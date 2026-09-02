<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Thinkycz\LaravelCore\Support\Typer;

/**
 * Resolves the active store for a request, applying the priority:
 *
 *   1. `?store_id=` query override (lets shared links jump straight to a store).
 *   2. `active_store` request attribute set by the ResolveActiveStore
 *      middleware (read from the current browser session).
 *   3. The first non-warehouse store the user owns; for limited users
 *      this is their assigned store.
 *
 * Limited users are pinned to their assigned store; the query override
 * is ignored for them.
 */
final class ActiveStoreResolver
{
    /**
     * Session key used to persist the active store per browser session.
     */
    public const string SESSION_KEY = 'active_store_id';

    /**
     * Queue context key carrying an encrypted originating session identifier.
     */
    public const string SESSION_ID_CONTEXT = 'active_store_browser_session_id';

    /**
     * Request attribute name used to pass the resolved store between
     * middleware and controllers.
     */
    public const string ATTRIBUTE = 'active_store';

    /**
     * Resolve the active store for the given request and user.
     *
     * Always returns null or a store owned by the user under the
     * standard `Store::scopeForUser` tenancy rule.
     */
    public static function resolve(Request $request, User $user): Store|null
    {
        if (!$user->isAdmin()) {
            return self::resolveForLimitedUser($user);
        }

        $requestedId = Typer::parseNullableInt($request->input('store_id'));

        if ($requestedId !== null) {
            return self::findOwned($user, $requestedId);
        }

        $attribute = $request->attributes->get(self::ATTRIBUTE);

        if ($attribute instanceof Store) {
            return $attribute;
        }

        return self::firstOwned($user);
    }

    /**
     * Resolve an explicitly requested historical store for read-only reports.
     */
    public static function resolveIncludingInactive(Request $request, User $user): Store|null
    {
        if ($user->isAdmin()) {
            $requestedId = Typer::parseNullableInt($request->input('store_id'));
            if ($requestedId !== null) {
                $query = Store::query();
                Store::scopeForUser($query, $user);
                $store = $query->whereKey($requestedId)->first();

                return $store instanceof Store ? $store : null;
            }
        }

        return self::resolve($request, $user);
    }

    /**
     * Resolve the active store for a limited (non-admin) user.
     *
     * Limited users are pinned to their assigned store; the session
     * and query override cannot redirect them to a different store.
     */
    private static function resolveForLimitedUser(User $user): Store|null
    {
        $assignedId = $user->getAssignedStoreId();

        if ($assignedId === null) {
            return null;
        }

        return self::findOwned(self::resolveScopeUser($user), $assignedId);
    }

    /**
     * The owner used for store scoping.
     *
     * For a limited user this is the admin (parent) so that the
     * limited user can browse the same stores that the admin owns.
     */
    private static function resolveScopeUser(User $user): User
    {
        return $user->resolveScopeUser();
    }

    /**
     * Find a single store by id within the user's tenancy scope.
     */
    private static function findOwned(User $user, int $storeId): Store|null
    {
        $query = Store::query();
        Store::scopeForUser($query, $user);
        Store::scopeActive($query);

        $store = $query->whereKey($storeId)->first();

        return $store instanceof Store ? $store : null;
    }

    /**
     * Return the user's preferred default store: first non-warehouse
     * (retail) store if any, otherwise the first owned store.
     */
    private static function firstOwned(User $user): Store|null
    {
        $query = Store::query();
        Store::scopeForUser($query, self::resolveScopeUser($user));
        Store::scopeActive($query);
        $stores = $query->orderBy('name')->get()->all();

        foreach ($stores as $store) {
            if (!$store->isWarehouse()) {
                return $store;
            }
        }

        return $stores[0] ?? null;
    }
}
