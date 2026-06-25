<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\User;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\UserValidity;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Resolver;

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

        $stores = Store::selectListForUser($admin);

        return Inertia::render('users/Edit', [
            'user' => [
                'id' => $user->getKey(),
                'email' => $user->getEmail(),
                'is_admin' => $user->isAdmin(),
                'assigned_store_id' => $user->getAssignedStoreId(),
            ],
            'stores' => $stores,
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
        }

        $validated = $this->validateRequest($request, $rules);

        DB::transaction(static function () use ($user, $validated, $admin, $isSelf): void {
            $attributes = [
                'email' => $validated->assertString('email'),
            ];

            $password = $validated->assertNullableString('password');

            if ($password !== null && $password !== '') {
                $attributes['password'] = $password;
            }

            if (!$isSelf) {
                $attributes['assigned_store_id'] = $validated->parseInt('assigned_store_id');
            }

            // Guard: an admin can never demote themselves.
            if ($user->is($admin)) {
                $attributes['is_admin'] = true;
                $attributes['parent_user_id'] = null;
                $attributes['assigned_store_id'] = null;
            }

            $user->update($attributes);
        });

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
}
