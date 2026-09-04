<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Enums\LimitedUserSectionEnum;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Typer;

class UserManagementService
{
    /**
     * Create a limited account using the same persisted user shape as the web form.
     */
    public function createUser(User $actor, string $email, string $password, Store $assignedStore): User
    {
        $this->authorizeStore($actor, $assignedStore);

        return DB::transaction(function () use ($actor, $email, $password, $assignedStore): User {
            $assignedStore = Typer::assertInstance(
                Store::query()->whereKey($assignedStore->getKey())->lockForUpdate()->firstOrFail(),
                Store::class,
            );
            $this->authorizeStore($actor, $assignedStore);
            if (!$assignedStore->isActive() || $assignedStore->isWarehouse()) {
                \abort(404);
            }

            return User::query()->create([
                'email' => $email,
                'password' => $password,
                'locale' => $actor->getLocale(),
                'is_admin' => false,
                'parent_user_id' => $actor->getKey(),
                'assigned_store_id' => $assignedStore->getKey(),
            ]);
        });
    }

    /**
     * Update an account managed by the main admin.
     *
     * @param list<string>|null $enabledSections
     */
    public function updateUser(
        User $actor,
        User $target,
        string $email,
        string|null $password,
        Store|null $assignedStore,
        array|null $enabledSections,
    ): User {
        $this->authorizeManagedUser($actor, $target);
        $isSelf = $target->is($actor);

        if (!$isSelf && !$assignedStore instanceof Store) {
            \abort(422);
        }

        DB::transaction(function () use ($actor, $target, $email, $password, $assignedStore, $enabledSections, $isSelf): void {
            if (!$isSelf) {
                $assignedStore = Typer::assertInstance(
                    Store::query()->whereKey($assignedStore->getKey())->lockForUpdate()->firstOrFail(),
                    Store::class,
                );
                $this->authorizeStore($actor, $assignedStore);
                if (!$assignedStore->isActive() || $assignedStore->isWarehouse()) {
                    \abort(404);
                }
            }

            $target = Typer::assertInstance(User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail(), User::class);
            $this->authorizeManagedUser($actor, $target);
            $attributes = ['email' => $email];

            if ($password !== null && $password !== '') {
                $attributes['password'] = $password;
            }

            if (!$isSelf) {
                $attributes['assigned_store_id'] = $assignedStore->getKey();

                if ($enabledSections !== null) {
                    $attributes['disabled_sections'] = \array_values(\array_diff(
                        LimitedUserSectionEnum::values(),
                        $enabledSections,
                    ));
                }
            } else {
                $attributes['is_admin'] = true;
                $attributes['parent_user_id'] = null;
                $attributes['assigned_store_id'] = null;
            }

            $target->update($attributes);
        });

        return $target->refresh();
    }

    /**
     * Delete a limited account managed by the main admin.
     */
    public function deleteUser(User $actor, User $target): bool
    {
        $this->assertAdmin($actor);

        if ($target->is($actor) || $target->isAdmin()) {
            return false;
        }

        $this->authorizeManagedUser($actor, $target);

        return $target->delete();
    }

    /**
     * Update the main admin's email and locale.
     */
    public function updateProfile(User $actor, string $email, string $locale): User
    {
        $this->assertAdmin($actor);
        $actor->update(['email' => $email, 'locale' => $locale]);

        return $actor->refresh();
    }

    /**
     * Ensure a user belongs to the main admin's managed tree.
     */
    private function authorizeManagedUser(User $actor, User $target): void
    {
        $this->assertAdmin($actor);

        if (!$target->is($actor) && $target->getParentUserId() !== $actor->getKey()) {
            \abort(403);
        }
    }

    /**
     * Ensure a store belongs to the main administrator.
     */
    private function authorizeStore(User $actor, Store $store): void
    {
        $this->assertAdmin($actor);

        if ($store->getUserId() !== $actor->getKey()) {
            \abort(404);
        }
    }

    /**
     * Ensure the assistant actor is the main administrator.
     */
    private function assertAdmin(User $actor): void
    {
        if (!$actor->isAdmin()) {
            \abort(403);
        }
    }
}
