<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\User;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\UserValidity;
use App\Models\Store;
use App\Models\User;
use App\Services\AdministrationManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Resolver;

class UserCreateController
{
    use ValidatesWebRequests;

    /**
     * Show the create user form.
     */
    public function create(): Response
    {
        $admin = User::mustAuth();
        $stores = $this->loadAdminStores($admin);

        return Inertia::render('users/Create', [
            'stores' => $stores,
        ]);
    }

    /**
     * Persist a new limited user.
     */
    public function store(Request $request): RedirectResponse
    {
        $admin = User::mustAuth();
        $validity = UserValidity::inject($admin->getKey());

        $validated = $this->validateRequest($request, [
            'email' => $validity->email()->required()->toArray(),
            'password' => $validity->password()->required()->confirmed()->toArray(),
            'assigned_store_id' => $validity->assignedStoreId()->required()->toArray(),
        ]);

        $store = Store::query()->where('user_id', $admin->getKey())->whereKey($validated->parseInt('assigned_store_id'))->firstOrFail();
        (new AdministrationManagementService())->createUser(
            $admin,
            $validated->assertString('email'),
            $validated->assertString('password'),
            $store,
        );

        Inertia::flash('success', \__('User created.'));

        return Resolver::resolveRedirector()->route('users.index');
    }

    /**
     * Load admin's stores for the assignment select.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function loadAdminStores(User $admin): array
    {
        $query = Store::query();
        Store::scopeForUser($query, $admin);

        return $query->orderBy('name')
            ->get()
            ->map(static fn(Store $store): array => [
                'id' => $store->getKey(),
                'name' => $store->getName(),
            ])
            ->all();
    }
}
