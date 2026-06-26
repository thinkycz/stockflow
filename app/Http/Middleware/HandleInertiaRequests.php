<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Store;
use App\Models\User;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): string|null
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'app' => [
                'name' => Config::inject()->assertString('app.name'),
                'locale' => Config::inject()->assertString('app.locale'),
                'locales' => Config::inject()->assertArray('app.locales'),
            ],
            'auth' => [
                'user' => fn(): array|null => $this->user(),
            ],
            'active_store' => fn(): array|null => $this->activeStore(),
            'available_stores' => fn(): array => $this->availableStores(),
            'flash' => [
                'success' => static fn(): string|null => self::flashMessage($request, 'success'),
                'error' => static fn(): string|null => self::flashMessage($request, 'error'),
            ],
            'ziggy' => fn(): array => [
                ...(new Ziggy())->toArray(),
                'location' => $request->url(),
            ],
        ];
    }

    /**
     * Resolve a flash message by key.
     *
     * Inertia v3 stores flash data under the dedicated `inertia.flash_data`
     * session key (see {@see Inertia::flash()}) and the Inertia middleware
     * reflashes the entry on every request. The Laravel session
     * `->flash('success', ...)` mechanism, by contrast, is consumed after a
     * single request and dies across an intermediate 302 redirect chain
     * (e.g. the authenticated visitor being bounced from /login to
     * /dashboard). We prefer the Inertia path so flashes survive the
     * 302 → guest-redirect → final render hop, and fall back to the
     * plain session key for same-request controllers that still use
     * `$request->session()->flash(...)`.
     */
    protected static function flashMessage(Request $request, string $key): string|null
    {
        $inertiaFlash = Inertia::getFlashed($request);

        if (isset($inertiaFlash[$key]) && \is_string($inertiaFlash[$key])) {
            return $inertiaFlash[$key];
        }

        return Typer::assertNullableString($request->session()->get($key));
    }

    /**
     * Resolve the authenticated user for shared Inertia props.
     *
     * @return array<string, mixed>|null
     */
    protected function user(): array|null
    {
        $user = Resolver::resolveAuthManager()->guard('users')->user();

        if ($user instanceof User === false) {
            return null;
        }

        return [
            'id' => $user->getKey(),
            'email' => $user->getEmail(),
            'locale' => $user->getLocale(),
            'email_verified_at' => $user->getEmailVerifiedAt()?->toJSON(),
            'is_admin' => $user->isAdmin(),
            'assigned_store_id' => $user->getAssignedStoreId(),
        ];
    }

    /**
     * Resolve the active store payload for Inertia clients.
     *
     * Mirrors {@see ActiveStoreResolver::resolve()} so the sidebar can
     * render the current selection consistently with what controllers
     * see in `$request->attributes->get(ActiveStoreResolver::ATTRIBUTE)`.
     *
     * @return array{id: int, name: string, is_warehouse: bool}|null
     */
    protected function activeStore(): array|null
    {
        $user = Resolver::resolveAuthManager()->guard('users')->user();

        if (!$user instanceof User) {
            return null;
        }

        $store = ActiveStoreResolver::resolve(\request(), $user);

        if (!$store instanceof Store) {
            return null;
        }

        return [
            'id' => $store->getKey(),
            'name' => $store->getName(),
            'is_warehouse' => $store->isWarehouse(),
        ];
    }

    /**
     * Resolve the list of stores the current user can switch between.
     *
     * Admins see all stores they own (warehouse first, then retail by
     * name); limited users get only their assigned store.
     *
     * @return array<int, array{id: int, name: string, is_warehouse: bool}>
     */
    protected function availableStores(): array
    {
        $user = Resolver::resolveAuthManager()->guard('users')->user();

        if (!$user instanceof User) {
            return [];
        }

        $query = Store::query();
        Store::scopeForUser($query, $user);

        if (!$user->isAdmin()) {
            $assignedId = $user->getAssignedStoreId();

            if ($assignedId === null) {
                return [];
            }

            $query->whereKey($assignedId);
        }

        $stores = $query
            ->orderByDesc('is_warehouse')
            ->orderBy('name')
            ->get()
            ->all();

        return \array_map(static fn(Store $store): array => [
            'id' => $store->getKey(),
            'name' => $store->getName(),
            'is_warehouse' => $store->isWarehouse(),
        ], $stores);
    }
}
