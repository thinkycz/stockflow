<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\User;

use App\Enums\LimitedUserSectionEnum;
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
use Thinkycz\LaravelCore\Support\Typer;

class UserEditController
{
    use ValidatesWebRequests;

    /**
     * Show the edit user form.
     */
    public function edit(User $user): Response
    {
        $admin = User::mustAuth();
        $this->ensureManaged($admin, $user);

        $stores = $this->loadAdminStores($admin);

        return Inertia::render('users/Edit', [
            'user' => [
                'id' => $user->getKey(),
                'email' => $user->getEmail(),
                'is_admin' => $user->isAdmin(),
                'assigned_store_id' => $user->getAssignedStoreId(),
                'enabled_sections' => $user->getEnabledSectionValues(),
            ],
            'stores' => $stores,
            'section_options' => LimitedUserSectionEnum::values(),
        ]);
    }

    /**
     * Update the user.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $admin = User::mustAuth();
        $this->ensureManaged($admin, $user);

        $validity = UserValidity::inject($admin->getKey());
        $isSelf = $user->is($admin);

        $rules = [
            'email' => $validity->email($user->getKey())->required()->toArray(),
        ];

        if ($isSelf) {
            // The main admin can change their own email/password but never role/store.
            $rules['password'] = $validity->password()->nullable()->confirmed()->toArray();
        } else {
            // Limited users: password optional, store assignment required.
            $rules['password'] = $validity->password()->nullable()->confirmed()->toArray();
            $rules['assigned_store_id'] = $validity->assignedStoreId()->required()->toArray();
            $rules['enabled_sections'] = $validity->enabledSections()->nullable()->present()->toArray();
            $rules['enabled_sections.*'] = $validity->enabledSection()->required()->toArray();
        }

        $validated = $this->validateRequest($request, $rules);

        $assignedStore = $isSelf
            ? null
            : Store::query()->where('user_id', $admin->getKey())->whereKey($validated->parseInt('assigned_store_id'))->firstOrFail();
        (new AdministrationManagementService())->updateUser(
            $admin,
            $user,
            $validated->assertString('email'),
            $validated->assertNullableString('password'),
            $assignedStore,
            $isSelf ? null : \array_values(Typer::assertStringArray($validated->assertArray('enabled_sections'))),
        );

        Inertia::flash('success', \__('User updated.'));

        return Resolver::resolveRedirector()->route('users.index');
    }

    /**
     * Ensure the target user belongs to the admin's tree (or is the admin).
     */
    private function ensureManaged(User $admin, User $target): void
    {
        if ($target->is($admin)) {
            return;
        }

        if ($target->getParentUserId() === $admin->getKey()) {
            return;
        }

        \abort(403);
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
