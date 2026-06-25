<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Concerns;

use App\Models\User;

trait ResolvesUserScope
{
    /**
     * Resolve the owner used for store / item scoping.
     *
     * For a limited user this is the admin (parent) so that the limited
     * user can browse the same data that the admin owns.
     */
    protected function resolveScopeUser(User $user): User
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
