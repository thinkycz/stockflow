<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\User;

use App\Models\User;
use App\Services\AdministrationManagementService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

class UserDestroyController
{
    /**
     * Delete a limited user. Refuses to delete the main admin.
     */
    public function __invoke(User $user): RedirectResponse
    {
        $admin = User::mustAuth();

        if (!(new AdministrationManagementService())->deleteUser($admin, $user)) {
            Inertia::flash('error', \__('You cannot delete the main admin.'));

            return Resolver::resolveRedirector()->route('users.index');
        }

        Inertia::flash('success', \__('User deleted.'));

        return Resolver::resolveRedirector()->route('users.index');
    }
}
