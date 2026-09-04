<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class AccountLifecycleService
{
    /**
     * Remove a limited account without permitting company-owner deletion.
     */
    public function deleteSelf(User $actor): void
    {
        DB::transaction(static function () use ($actor): void {
            $locked = User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->isAdmin()) {
                throw new AuthorizationException();
            }
            $locked->delete();
        });
    }
}
