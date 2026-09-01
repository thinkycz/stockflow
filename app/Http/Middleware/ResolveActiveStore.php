<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Store;
use App\Models\User;
use App\Support\ActiveStoreResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Support\Typer;

/**
 * Resolves the active store for the current request before
 * HandleInertiaRequests runs, so the resolved value can be shared with
 * every Inertia page.
 *
 * For admins this reads the active store from the browser session and
 * validates that the store still belongs to the user. For limited users
 * the assigned store is used unconditionally.
 */
class ResolveActiveStore
{
    /**
     * @param Closure(Request): SymfonyResponse $next
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $user = User::auth();

        if ($user instanceof User) {
            $store = $this->resolveForUser($user, $request);

            if ($store instanceof Store) {
                $request->attributes->set(ActiveStoreResolver::ATTRIBUTE, $store);
            }
        }

        return $next($request);
    }

    /**
     * Resolve the active store for the given user.
     *
     * Limited users are pinned to their assigned store; any stale active
     * store session value is cleared because they cannot switch stores.
     */
    private function resolveForUser(User $user, Request $request): Store|null
    {
        if (!$user->isAdmin()) {
            $request->session()->forget(ActiveStoreResolver::SESSION_KEY);

            return ActiveStoreResolver::resolve($request, $user);
        }

        $sessionId = Typer::parseNullableInt($request->session()->get(ActiveStoreResolver::SESSION_KEY));

        if ($sessionId !== null) {
            $match = $this->findOwned($user, $sessionId);

            if ($match instanceof Store) {
                return $match;
            }

            $request->session()->forget(ActiveStoreResolver::SESSION_KEY);
        }

        return ActiveStoreResolver::resolve($request, $user);
    }

    /**
     * Find a single store by id within the user's tenancy scope.
     */
    private function findOwned(User $user, int $storeId): Store|null
    {
        $query = Store::query();
        Store::scopeForUser($query, $user);

        $store = $query->whereKey($storeId)->first();

        return $store instanceof Store ? $store : null;
    }
}
