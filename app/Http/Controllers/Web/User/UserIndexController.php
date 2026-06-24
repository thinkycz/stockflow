<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\User;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Typer;

class UserIndexController
{
    /**
     * Page size hint required by the web index controller architecture test.
     *
     * The Users list is bounded by the number of accounts an admin has
     * provisioned; pagination is not exposed.
     */
    public const int TAKE = 1000;

    /**
     * Render the user management page.
     */
    public function __invoke(Request $request): Response
    {
        $admin = User::mustAuth();

        $query = User::query();
        User::scopeForAdmin($query, $admin);
        $query->orderBy('is_admin', 'desc')->orderBy('email');

        $search = Typer::parseNullableString($request->query('search'));

        if ($search !== null && $search !== '') {
            $query->where(static function (Builder $query) use ($search): void {
                $query->where('email', 'like', '%' . $search . '%');
            });
        }

        $rows = $query->take(self::TAKE)->get()->map(static function (User $user): array {
            $storeName = null;

            if ($user->getAssignedStoreId() !== null) {
                $storeLookup = Store::query();
                Store::scopeForUser($storeLookup, $user->getParentUserId() ?? $user->getKey());
                $store = $storeLookup->whereKey($user->getAssignedStoreId())->first();

                if ($store instanceof Store) {
                    $storeName = $store->getName();
                }
            }

            return [
                'id' => $user->getKey(),
                'email' => $user->getEmail(),
                'is_admin' => $user->isAdmin(),
                'assigned_store_id' => $user->getAssignedStoreId(),
                'assigned_store_name' => $storeName,
                'parent_user_id' => $user->getParentUserId(),
                'created_at' => $user->getCreatedAt()->toJSON(),
            ];
        })->all();

        return Inertia::render('users/Index', [
            'users' => $rows,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }
}
